
<!-- Add Postal Code Button -->
<div style="margin-top: 10px; margin-bottom: 10px;">
  <a href="{$addPostalCodeUrl}" class="crm-button" style="color: white; background-color: #007bff;">Add Postal Code</a>
</div>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Postal Code</th>
      <th>AAC Name</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    {foreach from=$postalCodes item=postalCode}
      <tr>
        <td>{$postalCode.id}</td>
        <td>{$postalCode.postal_code}</td>
        <td>{$postalCode.aac_name}</td>
        <td>
          <a href="{$baseUrl}&delete_id={$postalCode.id}" class="action-item delete-job crm-hover-button" onclick="return confirm('Are you sure you want to delete this postal code?');">
            <i class="crm-i fa-trash"></i> Delete
          </a>
        </td>
      </tr>
    {/foreach}
  </tbody>
</table>

