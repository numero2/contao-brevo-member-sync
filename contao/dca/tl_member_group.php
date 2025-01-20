<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
 */


use Contao\CoreBundle\DataContainer\PaletteManipulator;


/**
 * Modify the palettes
 */
$pm = PaletteManipulator::create()
    ->addLegend('brevo_legend', 'redirect_legend', PaletteManipulator::POSITION_AFTER)
    ->addField(['brevo_sync'], 'brevo_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_member_group')
;

$GLOBALS['TL_DCA']['tl_member_group']['palettes']['__selector__'][] = 'brevo_sync';
$GLOBALS['TL_DCA']['tl_member_group']['subpalettes']['brevo_sync'] = 'brevo_api_key,brevo_list_ids,brevo_mapping';


/**
 * Add fields
 */
$GLOBALS['TL_DCA']['tl_member_group']['fields']['brevo_sync'] = [
    'exclude'           => true
,   'inputType'         => 'checkbox'
,   'eval'              => ['submitOnChange'=>true]
,   'sql'               => "char(1) COLLATE ascii_bin NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_member_group']['fields']['brevo_api_key'] = [
    'exclude'           => true
,   'inputType'         => 'text'
,   'eval'              => ['mandatory'=>true, 'tl_class'=>'w50']
,   'sql'               => "varchar(128) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_member_group']['fields']['brevo_list_ids'] = [
    'exclude'           => true
,   'inputType'         => 'text'
,   'eval'              => ['tl_class'=>'w50']
,   'sql'               => "varchar(128) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_member_group']['fields']['brevo_mapping'] = [
    'exclude'           => true
,   'inputType'         => 'keyValueWizard'
,   'eval'              => ['tl_class'=>'clr']
,   'sql'               => "text NULL"
];
