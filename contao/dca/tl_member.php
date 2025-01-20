<?php

/**
 * Brevo member sync bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2024, numero2 - Agentur für digitales Marketing GbR
 */


/**
 * Add fields
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['brevo_ids'] = [
    'sql'               => "blob NULL"
];
