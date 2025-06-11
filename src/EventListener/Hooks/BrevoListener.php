<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BrevoMemberSyncBundle\EventListener\Hooks;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\FrontendUser;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\Module;
use Contao\StringUtil;
use numero2\BrevoMemberSyncBundle\API\BrevoListenerAPI;


class BrevoListener {


    /**
     * Create or update a user at Brevo
     *
     * @param integer|Contao\FrontendUser|Contao\MemberModel $id
     * @param array|Contao\Module $arrData
     * @param Contao\Module|null $module
     *
     * @Hook("createNewUser")
     * @Hook("updatePersonalData")
     * @Hook("activateAccount")
     */
    public function createUpdateMember( $id, $arrData, $module=null ): void {

        // activation, change parameters
        if( $id instanceof MemberModel ) {
            $module = $arrData;
            $arrData = null;
        }

        if( empty($module->brevo_sync) || empty($module->brevo_api_key) ) {
            return;
        }

        $api = new BrevoListenerAPI($module->brevo_api_key);

        $oUser = null;
        $brevoId = null;
        $blnUpdate = false;

        // mod_personaldata
        if( $id instanceof FrontendUser ) {

            $oUser = $id;

            $key = md5($module->brevo_api_key);
            $brevoIds = StringUtil::deserialize($oUser->brevo_ids, true);
            $brevoId = $brevoIds[$key];

            $blnUpdate = !empty($brevoId);

        // activation
        } else if( $id instanceof MemberModel ) {

            $oUser = $id;

        // mod_registration
        } else {

            $oUser = MemberModel::findById($id);

            if( $this->skipOnRegistration($module) ) {
                return;
            }
        }

        $listIds = array_map(function( $a ) {
            return intval(trim($a));
        }, explode(',', $module->brevo_list_ids));


        $contact = [
            'EMAIL' => $oUser->email
        ];

        $map = StringUtil::deserialize($module->brevo_mapping, true);

        foreach( $map as $row ) {

            $key = $row['key'];
            $value = $row['value'];
            $contact[$value] = $oUser->{$key};
        }

        $resultId = 0;
        if( $blnUpdate ) {
            $resultId = $api->updateContact($brevoId, $contact, $listIds);
        } else {
            $resultId = $api->createContact($contact, $listIds);
        }

        if( $resultId ) {

            $key = md5($module->brevo_api_key);
            $brevoIds = StringUtil::deserialize($oUser->brevo_ids, true);
            $brevoIds[$key] = $resultId;
            $oUser->brevo_ids = $brevoIds;

            $oUser->save();
        }
    }


    /**
     * Create or update a user at Brevo with data from member groups
     *
     * @param integer|Contao\FrontendUser|Contao\MemberModel $id
     * @param array|Contao\Module $arrData
     * @param Contao\Module|null $module
     *
     * @Hook("createNewUser")
     * @Hook("updatePersonalData")
     * @Hook("activateAccount")
     */
    public function createUpdateMemberForMemberGroups( $id, $arrData, $module=null ): void {

        // activation, change parameters
        if( $id instanceof MemberModel ) {
            $module = $arrData;
            $arrData = null;
        }

        if( empty($module->brevo_sync_with_member_groups) ) {
            return;
        }

        $userId = null;

        // mod_personaldata
        if( $id instanceof FrontendUser ) {

            $oUser = $id;
            $userId = $oUser->id;

        // activation
        } else if( $id instanceof MemberModel ) {

            $oUser = $id;
            $userId = $oUser->id;

        // mod_registration
        } else {

            $userId = $id;

            if( $this->skipOnRegistration($module) ) {
                return;
            }
        }

        if( $userId ) {
            $this->createUpdateMemberByMemberGroups($userId);
        }
    }


    /**
     * Create or update one member to Brevo by its member group configs
     *
     * @param string|int $memberId
     */
    public function createUpdateMemberByMemberGroups( $memberId ): void {

        $member = MemberModel::findOneById($memberId);
        if( !$member ) {
            return;
        }

        $groupIds = StringUtil::deserialize($member->groups, true);
        if( empty($groupIds) ) {
            return;
        }

        $groups = MemberGroupModel::findMultipleByIds($groupIds);
        if( $groups ) {
            $groups = $groups->fetchAll();
        }

        $configs = $this->mergeMemberGroupsConfigs($groups);
        if( empty($configs) ) {
            return;
        }

        $brevoIds = StringUtil::deserialize($member->brevo_ids, true);

        foreach( $configs as $key => $config ) {

            $api = new BrevoListenerAPI($config['brevo_api_key']);

            $contact = [
                'EMAIL' => $member->email
            ];
            $listIds = $config['brevo_list_ids'];

            foreach( $config['brevo_mapping'] as $k => $v ) {
                $contact[$v] = $member->{$k};
            }

            if( in_array($key, $brevoIds) ) {

                $api->updateContact($brevoIds[$key], $contact, $listIds);

            } else {

                $resultId = $api->createContact($contact, $listIds);

                if( $resultId ) {
                    $brevoIds[$key] = $resultId;
                }
            }
        }

        $member->brevo_ids = $brevoIds;
        $member->save();
    }


    /**
     * Merge multiple brevo configs from member groups
     *
     * @param array $groups
     *
     * @return array
     */
    private function mergeMemberGroupsConfigs( array $groups ): array {

        $merged = [];

        foreach( $groups as $group ) {

            if( empty($group['brevo_sync']) || empty($group['brevo_api_key']) ) {
                continue;
            }

            $key = md5($group['brevo_api_key']);

            if( !array_key_exists($key, $merged) ) {

                $merged[$key] = [
                    'brevo_api_key' => $group['brevo_api_key']
                ,   'brevo_list_ids' => [$group['brevo_list_ids']]
                ,   'brevo_mapping' => []
                ];

            } else {

                $merged[$key]['brevo_list_ids'][] = $group['brevo_list_ids'];
            }

            $map = StringUtil::deserialize($group['brevo_mapping'], true);

            foreach( $map as $keyValue ) {
                $merged[$key]['brevo_mapping'][$keyValue['key']] = $keyValue['value'];
            }
        }

        foreach( $merged as $key => $config ) {

            if( !empty($config['brevo_list_ids']) ) {

                $listIds = [];

                foreach( $config['brevo_list_ids'] as $k => $v ) {

                    $ids = array_map(function( $a ) {
                        return intval(trim($a));
                    }, explode(',', $v));

                    $listIds = array_merge($listIds, $ids);
                }

                $merged[$key]['brevo_list_ids'] = array_values(array_unique($listIds));
            }
        }

        return $merged;
    }


    /**
     * Check if the module configuration for the registration can be skipped as the sync will be done on activation
     *
     * @param Contao\Module $module
     *
     * @return bool
     */
    private function skipOnRegistration( Module $module ): bool {

        // if user will be activated after doi mail, first sync to brevo during activation
        // contao core
        if( $module->type === 'registration' && !empty($module->reg_activate) ) {
            return true;
        }
        // notification_center
        if( $module->type === 'registrationNotificationCenter' && !empty($module->nc_registration_auto_activate) ) {
            return true;
        }

        return false;
    }
}
