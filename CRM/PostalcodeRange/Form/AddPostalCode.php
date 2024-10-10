<?php

use CRM_PostalcodeRange_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_PostalcodeRange_Form_AddPostalCode extends CRM_Core_Form
{

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void
  {

    $editId = CRM_Utils_Request::retrieve('edit_id', 'Positive', CRM_Core_DAO::$_nullObject);
    $defaultValues = [];
    if ($editId) {
      $this->add('hidden', 'edit_id', $editId);
      // Fetch the postal code data to pre-fill the form
      $sql = "SELECT postal_code, aac_name FROM civicrm_aac_postal WHERE id = %1";
      $params = [1 => [$editId, 'Integer']];
      $dao = CRM_Core_DAO::executeQuery($sql, $params);

      if ($dao->fetch()) {
        Civi::log()->debug("This is dao : " . print_r($dao->postal_code, true));
        $defaultValues['postal_code'] = $dao->postal_code;
        $defaultValues['aac_name'] = $dao->aac_name;
      }
    }


    // Add Postal Code field
    $this->add(
      'text',
      'postal_code',
      'Postal Code',
      array('size' => 20, 'maxlength' => 6, 'required' => TRUE),
      // $defaultValues['postal_code'] ?? '' // Pre-fill postal code if available
    );

    // Add AAC Name field
    $this->add(
      'select',
      'aac_name',
      'AAC Name',
      $this->getContactOptions(),
      TRUE
    );

    // Add buttons
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'js' => array('onclick' => "window.location.href='" . CRM_Utils_System::url('civicrm/postalcode/search') . "'; return false;"),

      ),
    ));

    $this->setDefaults($defaultValues);
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }


  public function postProcess(): void
  {
    // Get the submitted form values
    $values = $this->exportValues();

    // Retrieve the display name for the selected AAC ID
    $aacId = $values['aac_name'];
    $result = civicrm_api4('Contact', 'get', [
      'select' => ['display_name'],
      'where' => [['id', '=', $aacId]],
    ]);
    //CRM_Core_Session::setStatus(ts(json_encode($result)),ts('Success'),'success'); 
    //CRM_Core_Session::setStatus(ts(json_encode($result[0]['display_name'])),ts('Success'),'success');

    $editId = CRM_Utils_Request::retrieve('edit_id', 'Positive', CRM_Core_DAO::$_nullObject);
    // Civi::log()->debug("This isvalues : " . print_r($values, true));

    if (!empty($values['edit_id'])) {
      $sql = "UPDATE civicrm_aac_postal 
      SET contact_id = %1, postal_code = %2, aac_name = %3 
      WHERE id = %4";
      $params = array(
        1 => array($aacId, 'Integer'),
        2 => array($values['postal_code'], 'String'),
        3 => array($result[0]['display_name'], 'String'),
        4 => array($editId, 'Integer'),
      );

      CRM_Core_DAO::executeQuery($sql, $params);
      CRM_Core_Session::setStatus(ts('Your data has been updated.'), ts('Success'), 'success');
    } else {


      // Insert data into your custom table
      $sql = "INSERT INTO civicrm_aac_postal (contact_id, postal_code, aac_name) VALUES (%1, %2, %3)";
      $params = array(
        1 => array($aacId, 'Integer'),
        2 => array($values['postal_code'], 'String'),
        3 => array($result[0]['display_name'], 'String'),
      );

      CRM_Core_DAO::executeQuery($sql, $params);

      CRM_Core_Session::setStatus(ts('Your data has been saved.'), ts('Success'), 'success');
    }
    // Redirect to the Find Postal Code page
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/postalcode/search'));

  }


  private function getContactOptions()
  {
    $options = [];

    // Using API v4 to get contact options
    $result = civicrm_api4('Contact', 'get', [
      'select' => ['id', 'display_name'],
      'where' => [['contact_type', '=', 'Organization']],
    ]);

    foreach ($result as $contact) {
      $options[$contact['id']] = $contact['display_name'];
    }

    return $options;
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames(): array
  {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
