<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2023, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BrevoMemberSyncBundle\EventListener\DataContainer;

use Brevo\Client\Api\AccountApi;
use Brevo\Client\Configuration;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Exception;
use GuzzleHttp\Client;


class ModuleListener {


    /**
     * Check if the given api key is valid
     *
     * @param string $varValue
     * @param Contao\DataContainer $dc
     *
     * @return string
     *
     * @throws Exception
     *
     * @Callback(table="tl_module", target="fields.brevo_api_key.save")
     */
    public function checkApiKey( string $varValue, DataContainer $dc ): string {

        if( !empty($varValue) ) {

            // Configure API key authorization: api-key
            $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $varValue);

            $apiInstance = new AccountApi(new Client(), $config);

            try {
                $apiInstance->getAccount();
            } catch( Exception $e ) {
                throw new Exception('Error from Brevo API: '. $e->getMessage());
            }
        }

        return $varValue;
    }
}
