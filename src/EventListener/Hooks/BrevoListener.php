<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2023, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BrevoMemberSyncBundle\EventListener\Hooks;

use Brevo\Client\Api\ContactsApi;
use Brevo\Client\ApiException;
use Brevo\Client\Configuration;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\UpdateContact;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\System;
use Exception;
use GuzzleHttp\Client;


class BrevoListener {


    /**
     * Sets email as username for frontend user
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

        // Configure API key authorization: api-key
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $module->brevo_api_key);

        $apiInstance = new ContactsApi(new Client(), $config);

        $oUser = null;
        $brevoId = null;
        $brevoContact = null;

        // mod_personaldata
        if( $id instanceof FrontendUser ) {

            $oUser = $id;
            $brevoId = $oUser->brevo_id;
            $brevoContact = new UpdateContact();

            if( empty($brevoId) ) {

                $brevoContact = new CreateContact();
                $brevoContact['email'] = $oUser->email;
            }

        // mod_registration
        } else {

            $oUser = MemberModel::findById($id);

            if( !empty($arrData['email']) ) {

                $brevoContact = new CreateContact();
                $brevoContact['email'] = $arrData['email'];
            }
        }

        $brevoContact['listIds'] = array_map(function( $a ) {
            return intval(trim($a));
        }, explode(',', $module->brevo_list_ids));

        $attributes = [];
        $map = StringUtil::deserialize($module->brevo_mapping, true);

        foreach( $map as $row ) {
            $key = $row['key'];
            $value = $row['value'];
            $attributes[$value] = $oUser->{$key};
        }

        if( empty($attributes) ) {
            $attributes['EMAIL'] = $oUser->email;
        }
        $brevoContact['attributes'] = $attributes;

        try {

            if( $brevoContact instanceof UpdateContact ) {

                $apiInstance->updateContact($brevoId, $brevoContact);

            } else if( $brevoContact instanceof CreateContact) {

                $result = $apiInstance->createContact($brevoContact);

                $oUser->brevo_id = $result->getId();
                $oUser->save();
            }

        } catch( Exception $e ) {

            if( $e instanceof ApiException ) {
                // if contact already exist get id and save it
                if( strpos($e->getResponseBody(), "Contact already exist") !== false ) {

                    $result = $apiInstance->getContactInfo($brevoContact['email']);

                    $oUser->brevo_id = $result->getId();
                    $oUser->save();

                    $this->createUpdateMember($id, $arrData, $module);
                    return;

                // if contact does not exist remove id
                } else if( strpos($e->getResponseBody(), "Contact does not exist") !== false ) {

                    $oUser->brevo_id = '';
                    $oUser->save();

                    $this->createUpdateMember($id, $arrData, $module);
                    return;
                }
            }

            System::log('Exception calling Brevo API: ', $e->getMessage(), __METHOD__, TL_ERROR);
        }
    }
}
