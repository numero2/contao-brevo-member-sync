<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\BrevoMemberSyncBundle\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Input;
use Contao\System;
use numero2\BrevoMemberSyncBundle\EventListener\Hooks\BrevoListener;


class MemberListener {


    /**
     * @var numero2\BrevoMemberSyncBundle\EventListener\Hooks\BrevoListener
     */
    private $brevoListener;


    public function __construct( BrevoListener $brevoListener ) {

        $this->brevoListener = $brevoListener;
    }


    /**
     * Sync the member submited in the backend to brevo
     *
     * @param Contao\DataContainer|Contao\FrontendUser $dc
     *
     * @return string
     *
     * @Callback(table="tl_member", target="config.onsubmit")
     */
    public function submitMember( $dc ): void {

        if( !$dc instanceof DataContainer ) {
            return;
        }

        $this->brevoListener->createUpdateMemberByMemberGroups($dc->id);
    }


    /**
     * Add and handles the brevo sync button to the select section
     *
     * @param array $buttons
     * @param Contao\DataContainer|Contao\FrontendUser $dc
     *
     * @return string
     *
     * @Callback(table="tl_member", target="select.buttons")
     */
    public function brevoSyncSelectButton( $buttons, DataContainer $dc ): array {

        if( class_exists(ContaoCorePermissions::class) ) {
            if( !System::getContainer()->get('security.helper')->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, 'tl_member::groups')) {
                return $buttons;
            }
        } else { // fallback for 4.9
            $user = System::importStatic(BackendUser::class);
            if( !$user->hasAccess('tl_page::alias', 'alexf') ) {
                return $buttons;
            }
        }

        // start sync for each member
        if( Input::post('FORM_SUBMIT') == 'tl_select' && isset($_POST['brevo_sync']) ) {

            $objSession = System::getContainer()->get('session');
            $session = $objSession->all();
            $ids = $session['CURRENT']['IDS'] ?? [];

            foreach( $ids as $id ) {
                $this->brevoListener->createUpdateMemberByMemberGroups($id);
            }

            Controller::redirect(Controller::getReferer());
        }

        // add the button
        $buttons['brevo_sync'] = '<button type="submit" name="brevo_sync" id="brevo_sync" class="tl_submit" accesskey="b">' . $GLOBALS['TL_LANG']['MSC']['brevoSyncSelected'] . '</button> ';

        return $buttons;
    }
}
