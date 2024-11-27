<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BrevoMemberSyncBundle\API;

use Brevo\Client\Api\AttributesApi;
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\ApiException;
use Brevo\Client\Configuration;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\UpdateContact;
use Contao\Date;
use Contao\System;
use Contao\Validator;
use Exception;
use GuzzleHttp\Client;


class BrevoListenerAPI {


    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var array
     */
    protected $cache;


    public function __construct( string $apiKey ) {

        $this->apiKey = $apiKey;
        $this->logger = System::getContainer()->get('monolog.logger.contao');

        $this->cache = [];
    }


    /**
     * Create a contact with the given data at brevo, which is added to the given lists.
     *
     * @param array $contact
     * @param array $listIds
     *
     * @return int
     */
    public function createContact( array $contact, array $listIds ): int {

        if( empty($contact['EMAIL']) ) {
            return 0;
        }

        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->apiKey);

        $apiContact = new ContactsApi(new Client(), $config);

        $brevoContact = new CreateContact();
        $brevoContact['email'] = $contact['EMAIL'];

        if( !empty($listIds) ) {
            $brevoContact['listIds'] = array_map(function( $a ) {
                return intval(trim($a));
            }, $listIds);
        }

        $attributes = [];
        $attributeConfig = $this->getContactAttributesConfig();

        foreach( $contact as $key => $value ) {

            if( empty($attributeConfig[$key]) ) {
                continue;
            }

            $cast = $this->convertFieldForBrevo($value, $attributeConfig[$key]);

            if( $cast === null ) {
                continue;
            }

            $attributes[$key] = $cast;
        }

        $brevoContact['attributes'] = $attributes;

        try {

            $result = $apiContact->createContact($brevoContact);

            return $result->getId();

        } catch( Exception $e ) {

            if( $e instanceof ApiException ) {
                // if contact already exist get id and save it
                if( strpos($e->getResponseBody(), "Contact already exist") !== false ) {

                    $result = $apiContact->getContactInfo($brevoContact['email']);

                    $id = $result->getId();

                    return $this->updateContact($id, $contact, $listIds);
                }
            }

            $this->logger->error('Brevo API error while creating contact: '. $e->getMessage());
        }

        return 0;
    }


    /**
     * Update the given contact at brevo, if no list is given this remains unchanged, otherwise the will be overridden.
     *
     * @param string $brevoId
     * @param array $contact
     * @param array $listIds
     *
     * @return int
     */
    public function updateContact( string $brevoId, array $contact, array $listIds=[] ): int {

        if( empty($brevoId) || empty($contact['EMAIL']) ) {
            return 0;
        }

        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->apiKey);

        $apiContact = new ContactsApi(new Client(), $config);

        $brevoContact = new UpdateContact();
        $brevoContact['email'] = $contact['EMAIL'];

        $brevoContact['listIds'] = array_map(function( $a ) {
            return intval(trim($a));
        }, $listIds);

        $attributes = [];
        $attributeConfig = $this->getContactAttributesConfig();

        foreach( $contact as $key => $value ) {

            if( empty($attributeConfig[$key]) ) {
                continue;
            }

            $cast = $this->convertFieldForBrevo($value, $attributeConfig[$key]);

            if( $cast === null ) {
                continue;
            }

            $attributes[$key] = $cast;
        }

        $brevoContact['attributes'] = $attributes;

        try {

            $apiContact->updateContact($brevoId, $brevoContact);

            return $brevoId;

        } catch( Exception $e ) {

            $this->logger->error('Brevo API error while updating contact: '. $e->getMessage());
        }

        return 0;
    }


    /**
     * Get the contact attributes and its configuration from brevo
     *
     * @return array
     */
    public function getContactAttributesConfig(): array {

        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->apiKey);

        // use cache if possible
        if( array_key_exists('contactAttributes', $this->cache) ) {
            return $this->cache['contactAttributes'];
        }

        $apiAttribute = new AttributesApi(new Client(), $config);

        try {

            $result = $apiAttribute->getAttributes();

            $attributes = [];
            foreach( $result->getAttributes() as $att ) {

                $attribute = [
                    'name' => $att->getName(),
                    'category' => $att->getCategory(),
                    'type' => $att->getType(),
                    'enumeration' => $att->getEnumeration(),
                    'calculatedValue' => $att->getCalculatedValue(),
                ];

                if( $attribute['enumeration'] && is_array($attribute['enumeration']) ) {
                    $attribute['enumeration'] = array_map(function( $a ) {
                        return ['value' => $a->getValue(), 'label' => $a->getLabel()];
                    }, $attribute['enumeration']);
                }

                $attributes[$att->getName()] = $attribute;
            }

            $this->cache['contactAttributes'] = $attributes;

            return $attributes;

        } catch( Exception $e ) {
            $this->logger->error('Brevo API error while getting contact attributes: '. $e->getMessage());
        }

        return [];
    }


    /**
     * Convert the given value into the format need by brvo
     *
     * @param mixed $value
     * @param array $typeConfig
     *
     * @return mixed
     */
    protected function convertFieldForBrevo( $value, array $typeConfig ) {

        if( $typeConfig['calculatedValue'] !== null ) {
            return null;
        }

        if( $value === '' ) {
            return '';
        }

        if( $typeConfig['type'] === 'date' ) {

            $date = null;
            if( Validator::isDate($value) ) {
                $oDate = new Date($value, Date:: getNumericDateFormat());
                $date = $oDate->timestamp;
            } else if( Validator::isDatim($value) ) {
                $oDate = new Date($value, Date:: getNumericDatimFormat());
                $date = $oDate->timestamp;
            } else if( Validator::isNatural($value) ) {
                $date = $value;
            }

            if( $date === null ) {
                return null;
            }

            return date('Y-m-d', $date);

        } else if( $typeConfig['type'] === 'float' ) {

            return floatval($value);

        } else if( $typeConfig['type'] === 'boolean' ) {

            return !empty($value);

        } else if( $typeConfig['type'] === null && !empty($typeConfig['enumeration']) ) {

            foreach( $typeConfig['enumeration'] as $key => $option ) {
                if( $option['label'] === $value ) {
                    return $option['value'];
                }
            }

            return null;
        }

        return $value;
    }
}
