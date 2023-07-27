<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2023, numero2 - Agentur für digitales Marketing GbR
 */


$GLOBALS['TL_DCA']['tl_member']['fields']['brevo_id'] = [
    'inputType'         => 'text'
,   'eval'              => ['tl_class'=>'w50']
,   'sql'               => "varchar(64) NOT NULL default ''"
];
