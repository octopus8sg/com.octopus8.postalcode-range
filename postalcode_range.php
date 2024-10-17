<?php

require_once 'postalcode_range.civix.php';
global $contactPostalCode;

use CRM_PostalcodeRange_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function postalcode_range_civicrm_config(&$config): void
{
  _postalcode_range_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function postalcode_range_civicrm_install(): void
{
  _postalcode_range_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function postalcode_range_civicrm_enable(): void
{
  _postalcode_range_civix_civicrm_enable();

  // Define the tags you want to create
  $tags = [
    'In_service_boundary',
    'Out_of_service_boundary'
  ];

  foreach ($tags as $tagName) {
    // Check if the tag already exists
    $existingTag = civicrm_api4('Tag', 'get', [
      'select' => ['id'],
      'where' => [
        ['name', '=', $tagName],
      ],
    ])->first();

    // If the tag does not exist, create it
    if (empty($existingTag)) {
      civicrm_api4('Tag', 'create', [
        'values' => [
          'name' => $tagName,
          'description' => "{$tagName} tag created by my extension",
          'used_for' => 'civicrm_contact', // Adjust this based on your use case
        ],
      ]);
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
/*
function postalcode_range_civicrm_navigationMenu(&$menu)
{
  if (!CRM_Core_Permission::check('administer CiviCRM')) {
    CRM_Core_Session::setStatus('', ts('Insufficient permission'), 'error');
    return;
  }
  //    $currentUserId = CRM_Core_Session::getLoggedInContactID();

  _postalcode_range_civix_insert_navigation_menu($menu, '', array(
    'label' => E::ts('Postal Code'),
    'name' => 'postalcode',
    'icon' => 'crm-i fa-map-marker',
    'url' => 'civicrm/postalcode',
    'permission' => 'access CiviCRM',
    'navID' => 10,
    'operator' => 'OR',
    'separator' => 0,
  ));
  _postalcode_range_civix_navigationMenu($menu);

  _postalcode_range_civix_insert_navigation_menu($menu, 'postalcode', array(
    'label' => E::ts('Add Postal Code'),
    'name' => 'postalcode_add',
    'url' => 'civicrm/postalcode',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _postalcode_range_civix_navigationMenu($menu);

  _postalcode_range_civix_insert_navigation_menu($menu, 'postalcode', array(
    'label' => E::ts('Find Postal Code'),
    'name' => 'postalcode_find',
    'url' => 'civicrm/postalcode/search',
    'permission' => 'access CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _postalcode_range_civix_navigationMenu($menu);
}
*/


/**
 * Implements hook_civicrm_post().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post
 */


function postalcode_range_civicrm_pre($op, $objectName, $id, &$params)
{
  if ($objectName == 'Individual' && $op == 'edit') {
    // Check if the postal code is present in the form data
    // Civi::log()->debug("This is the params pre : " . print_r($params, true));
    $inServiceTagId = getTagIdByName('In_service_boundary');
    $outOfServiceTagId = getTagIdByName('Out_of_service_boundary');
    $receiveTags = explode(',', $params['tag']);
    $receiveTags = array_diff($receiveTags, [(string) $inServiceTagId, (string) $outOfServiceTagId]);

    if (!empty($params['address'][1]['postal_code'])) {
      $postalCode = $params['address'][1]['postal_code'];

      $correctTagId = checkingInOrOutOfServiceBoundaryUpdated($postalCode);

    } else {
      // If postal code is not set or null, assign 'out-of-service-boundary' tag
      // $params['tag'] = 7; // Out of service boundary
      $correctTagId = '';
    }
    $receiveTags[] = (string) $correctTagId;
    $params['tag'] = implode(',', $receiveTags);
  }
}


function postalcode_range_civicrm_post($op, $objectName, $objectId, &$objectRef)
{
  Civi::log()->debug("op: " . $op . ", objectName: " . $objectName . ", objectid: " . $objectId);
  // Make sure we're working with an Individual contact on creation or update
  if ($objectName == 'Individual' && $op == 'create') {

    // Fetch the contact's postal code from the address
    $addresses = civicrm_api4('Address', 'get', [
      'select' => ['postal_code', 'contact_id'],
      'where' => [['contact_id', '=', $objectId]],
      'checkPermissions' => FALSE,
    ]);

    if (!empty($addresses)) {
      $postalCode = $addresses[0]['postal_code'];

      // Determine if the postal code is in-service or out-of-service
      if (!empty($postalCode)) {
        $correctTagId = checkingInOrOutOfServiceBoundaryUpdated($postalCode);

        // Assign the correct tag using the EntityTag API
        if ($correctTagId) {
          civicrm_api4('EntityTag', 'create', [
            'values' => [
              'entity_id' => $objectId, // Use the contact ID available in post hook
              'tag_id' => $correctTagId,
              'entity_table' => 'civicrm_contact',
            ],
          ]);
        }
      }
    }
  }
}

function checkingInOrOutOfServiceBoundaryUpdated($postalCode)
{

  $contactPostalCode = $postalCode;

  //getting tag ID 
  $inServiceTagId = getTagIdByName('In_service_boundary');
  $outOfServiceTagId = getTagIdByName('Out_of_service_boundary');

  //get all the AAC with the postal codes 
  $aacPostalCodes = civicrm_api4('Contact', 'get', [
    'select' => [
      'organization_name',
      'Organization_AAC_Details.List_of_Postal_Code_Serve',
    ],
    'where' => [
      ['contact_type', '=', 'Organization'],
      ['contact_sub_type', '=', 'AAC'],
    ],
    'checkPermissions' => TRUE,
  ]);

  Civi::log()->debug("AAC Postal Codes : " . print_r($aacPostalCodes, true));

  //Running though AAC one by one and checking whether the postal code matches. 
  foreach ($aacPostalCodes as $org) {
    // Split the postal code list into an array
    $serviceBoundaryPostalCodes = explode(',', str_replace(' ', '', $org["Organization_AAC_Details.List_of_Postal_Code_Serve"]));
    Civi::log()->debug("AAC Postal Codes serviceBoundaryPostalCodes of each: " . print_r($serviceBoundaryPostalCodes, true));

    // Check if the contact postal code is in the list
    $tagId = in_array($contactPostalCode, $serviceBoundaryPostalCodes) ? $inServiceTagId : $outOfServiceTagId;
    if ($tagId == $inServiceTagId) {
      return $tagId;
    }
  }
  return $tagId;
  // Return null if no match is found
  // return null;
}


function checkingOutOrInServiceBoundary($postalCode)
{
  $contactPostalCode = $postalCode;

  // Step 2: Retrieve all postal codes from the custom table
  $query = "SELECT postal_code FROM civicrm_aac_postal";
  $dao = CRM_Core_DAO::executeQuery($query);
  $serviceBoundaryPostalCodes = [];
  while ($dao->fetch()) {
    $serviceBoundaryPostalCodes[] = $dao->postal_code;
    Civi::log()->debug('Table Postal:' . implode(', ', $serviceBoundaryPostalCodes));
  }

  // Step 3: Get tag IDs
  $inServiceTagId = getTagIdByName('In_service_boundary');
  $outOfServiceTagId = getTagIdByName('Out_of_service_boundary');

  // Step 4: Compare postal codes and assign tags

  $tagId = in_array($contactPostalCode, $serviceBoundaryPostalCodes) ? $inServiceTagId : $outOfServiceTagId;
  Civi::log()->debug('Correct Tag ID', ['tagId' => $tagId]);

  return $tagId;
}


function getTagIdByName($tagName)
{
  $tag = civicrm_api4('Tag', 'get', [
    'select' => ['id'],
    'where' => [['name', '=', $tagName]],
  ]);
  if (empty($tag)) {
    throw new Exception("Tag not found: $tagName");
  }
  return $tag[0]['id'];
}

