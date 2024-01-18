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
use Contao\MemberModel;
use Contao\StringUtil;
use numero2\BrevoMemberSyncBundle\API\BrevoListenerAPI;


class BrevoListener {


    /**
     * Create or update a user at Brevo
     *
     * @param integer|\Contao\FrontendUser $id
     * @param array $arrData
     * @param Contao\Module $module
     *
     * @Hook("createNewUser")
     * @Hook("updatePersonalData")
     */
    public function createUpdateMember( $id, $arrData, $module ): void {

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
            $brevoId = $oUser->brevo_id;
            $blnUpdate = !empty($brevoId);

        // mod_registration
        } else {

            $oUser = MemberModel::findById($id);
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
            $oUser->brevo_id = $resultId;
            $oUser->save();
        }
    }
}
