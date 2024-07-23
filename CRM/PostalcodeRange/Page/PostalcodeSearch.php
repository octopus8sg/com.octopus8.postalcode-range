<?php

use CRM_PostalcodeRange_ExtensionUtil as E;

class CRM_PostalcodeRange_Page_PostalcodeSearch extends CRM_Core_Page {

  public function run() 
  {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Search Postal code'));

    // Handle delete action
    $deleteId = CRM_Utils_Request::retrieve('delete_id', 'Positive', CRM_Core_DAO::$_nullObject);
    if ($deleteId) {
      $this->deletePostalCode($deleteId);
    }

    // Get the filter input
    //$aacNameFilter = CRM_Utils_Request::retrieve('aac_name', 'String', CRM_Core_DAO::$_nullObject);

    // Build the query
    $params = [];
    $whereClause = '';

    // Execute the query
    $query = "
      SELECT id, postal_code, aac_name
      FROM civicrm_aac_postal
      $whereClause
      ORDER BY id";
    
    $dao = CRM_Core_DAO::executeQuery($query, $params);

    $postalCodes = [];
    while ($dao->fetch()) {
      $postalCodes[] = [
        'id' => $dao->id,
        'postal_code' => $dao->postal_code,
        'aac_name' => $dao->aac_name,
      ];
    }

    // Generate base URL for delete action
    $baseUrl = CRM_Utils_System::url('civicrm/postalcode/search', null, true);

    // Generate URL for adding a postal code
    $addPostalCodeUrl = CRM_Utils_System::url('civicrm/postalcode', null, true);
    
    // Assign variables for use in a template
    $this->assign('postalCodes', $postalCodes);
    //$this->assign('aacNameFilter', $aacNameFilter);
    $this->assign('baseUrl', $baseUrl);
    $this->assign('addPostalCodeUrl', $addPostalCodeUrl);

    parent::run();
  }

  private function deletePostalCode($id) {
    $sql = "DELETE FROM civicrm_aac_postal WHERE id = %1";
    $params = [1 => [$id, 'Integer']];
    CRM_Core_DAO::executeQuery($sql, $params);
    CRM_Core_Session::setStatus(ts('Postal code has been deleted.'), ts('Success'), 'success');
  }
}
