<?php

require_once 'postalcode_range.civix.php';

use CRM_PostalcodeRange_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function postalcode_range_civicrm_config(&$config): void {
  _postalcode_range_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function postalcode_range_civicrm_install(): void {
  _postalcode_range_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function postalcode_range_civicrm_enable(): void {
  _postalcode_range_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
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



/**
 * Implements hook_civicrm_post().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post
 */
function postalcode_range_civicrm_post(string $op, string $objectName, int $objectId, &$objectRef) {
  Civi::log()->info('Operation: ' . $op . ', Object Name: ' . $objectName);

  if (($objectName == 'Address' || $objectName == 'Contact') && ($op == 'create' || $op == 'edit')) {
    Civi::log()->info('Processing address for object ID: ' . $objectId);
    updateContactTagsBasedOnPostalCodes($objectId);
  } else {
    Civi::log()->info('Post hook works, but object is not Address.');
  }  
}

function updateContactTagsBasedOnPostalCodes($addressId) {
  Civi::log()->info('Address id:' . $addressId );

  // Step 1: Retrieve all contacts with their postal codes
  $addresses = civicrm_api4('Address', 'get', [
      'select' => [
          'postal_code',
          'contact_id',
      ],
      'where' => [
          ['id', '=', $addressId],
          ['postal_code', 'IS NOT NULL'],
      ],
      'checkPermissions' => FALSE,
  ]);

  if (empty($addresses)) {
    CRM_Core_Error::debug_log_message("updateContactTagsBasedOnPostalCodes: No addresses found for Contact ID $addressId");
    return;
  }

  //$postalcode = $addresses['postal_code'];
  Civi::log()->debug('Contact ID:' . $addresses[0]['contact_id'] );

  $contactId = $addresses[0]['contact_id'];
  $contactPostalCode = $addresses[0]['postal_code'];

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
    //Civi::log()->debug('contact Postal:' . $contactPostalCode );
    //Civi::log()->debug('contact id:' . $contact_Id );
    //Civi::log()->debug('contact id:' . $tagId );
    
    CRM_Core_Session::setStatus(ts(json_encode($tagId)),ts('Info'),'info');

    // Remove old tags
    removeServiceBoundaryTags($contactId, [$inServiceTagId, $outOfServiceTagId]);

    // Assign new tag
    assignTagToContact($contactId, $tagId);
}

function getTagIdByName($tagName) {
  $tag = civicrm_api4('Tag', 'get', [
      'select' => ['id'],
      'where' => [['name', '=', $tagName]],
  ]);
  if (empty($tag)) {
      throw new Exception("Tag not found: $tagName");
  }
  return $tag[0]['id'];
}


function removeServiceBoundaryTags($contactId, $tagIds) {
  foreach ($tagIds as $tagId) {
    $existingTag = civicrm_api4('EntityTag', 'get', [
        'select' => ['id'],
        'where' => [
            ['entity_table', '=', 'civicrm_contact'],
            ['entity_id', '=', $contactId],
            ['tag_id', '=', $tagId],
        ],
        'checkPermissions' => FALSE,
    ]);
    CRM_Core_Session::setStatus(ts(json_encode($contactId)),ts('Info'),'info');
    CRM_Core_Session::setStatus(ts(json_encode($tagId)),ts('Info'),'info');

    if (!empty($existingTag)) {
      civicrm_api4('EntityTag', 'delete', [
          'where' => [
              ['entity_table', '=', 'civicrm_contact'],
              ['entity_id', '=', $contactId],
              ['tag_id', '=', $tagId],
          ],
          'checkPermissions' => FALSE,
      ]);
    } 
  }
}

function assignTagToContact($contactId, $tagId) {
  Civi::log()->debug("hi");
  Civi::log()->debug('contact id:' . $contactId );
  Civi::log()->debug('tag id:' . $tagId );

    $result= civicrm_api4('EntityTag', 'create', [
      'values' => [
          'entity_table' => 'civicrm_contact',
          'entity_id' => $contactId,
          'tag_id' => $tagId,
      ],
      'checkPermissions' => FALSE,
  ]);
  Civi::log()->debug("result   " . count($result[0]));
}

/* function removeServiceBoundaryTags($contactId, $tagIds) {
  foreach ($tagIds as $tagId) {
    $existingTags = civicrm_api4('EntityTag', 'get', [
      'select' => ['id'],
      'where' => [
        ['entity_table', '=', 'civicrm_contact'],
        ['entity_id', '=', $contactId],
        ['tag_id', '=', $tagId],
      ],
    ]);

    Civi::log()->debug('Existing Tags to remove for Contact ID: ' . $contactId . ', Tag ID: ' . $tagId . ': ' . json_encode($existingTags));

    if (!empty($existingTags)) {
      foreach ($existingTags as $tag) {
        civicrm_api4('EntityTag', 'delete', [
          'where' => [
            ['id', '=', $tag['id']],
          ],
        ]);
      }
    }
  }
}
 */




