<?php
class MyAccount {
	public $settings = array(
		'name' => 'My Account',
		'user_menu_name' => 'My Account',
		'user_menu_icon' => '<i class="icon-user"></i>',
		'description' => 'Allows users to view and edit their account information.',
		'allowed_tags' => '<p><a><strong><u><blockquote><ul><ol><li><h2><h3><s><em><img><br>',
	);
	function user_area() {
		global $billic, $db;
		$billic->force_login();
		if (isset($_GET['Action']) && $_GET['Action'] == 'AddFunds') {
			if (get_config('myaccount_addfunds_enable') != 1) {
				err('Add Funds is disabled');
			}
			$billic->set_title('Add Funds');
			echo '<h1><i class="icon-money-banknote"></i> Add Funds</h1>';
			$billic->module('Invoices');
			if (isset($_POST['addcredit'])) {
				$_POST['amount'] = round($_POST['amount'], 2);
				if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
					$billic->error('Amount is invalid', 'amount');
				}
				if ($_POST['amount'] < get_config('myaccount_addfunds_min')) {
					$billic->error('The minimum amount is ' . get_config('billic_currency_prefix') . get_config('myaccount_addfunds_min') . get_config('billic_currency_suffix') , 'amount');
				}
				if ($_POST['amount'] > get_config('myaccount_addfunds_max')) {
					$billic->error('The maximum amount is ' . get_config('billic_currency_prefix') . get_config('myaccount_addfunds_max') . get_config('billic_currency_suffix') , 'amount');
				}
				if ($_SESSION['captcha'] != $_POST['captcha']) {
					unset($_SESSION['captcha']);
					$billic->error('Captcha code invalid, please try again', 'captcha');
				}
				$tax_group = get_config('myaccount_addfunds_tax_group');
				if ($tax_group == 'None') {
					$tax_group = '';
				}
				if (empty($billic->errors)) {
					unset($_SESSION['captcha']);
					$time = time();
					$invoiceid = $billic->modules['Invoices']->generate(array(
						'service' => 'credit',
						'user' => $billic->user,
						'duedate' => NULL,
						'amount' => $_POST['amount'],
						'tax_group' => $tax_group,
					));
					$billic->redirect('/User/Invoices/ID/' . $invoiceid . '/');
				}
			}
			$billic->show_errors();
			echo '<form method="POST"><table class="table table-striped" style="width: 200px">
			<tr><td' . $billic->highlight('amount') . ' align="right">Amount to pay:</td><td><div class="input-group"><span class="input-group-addon">' . get_config('billic_currency_prefix') . '</span><input type="text" class="form-control" name="amount" value="' . safe($_POST['amount']) . '" style="width:100px"><span class="input-group-addon">' . get_config('billic_currency_suffix') . '</span></div></td></tr>
			<tr><td><img src="/Captcha/' . time() . '" width="150" height="75" alt="CAPTCHA"></td><td' . $billic->highlight('captcha') . '>Enter&nbsp;the&nbsp;number&nbsp;you&nbsp;see<br><input type="text" class="form-control" name="captcha" size="6" style="text-align:center;width:150px" value="' . (empty($billic->errors['captcha']) ? safe($_POST['captcha']) : '') . '" /></td></tr>
			<tr><td colspan="2" align="center"><input type="submit" class="btn btn-success" name="addcredit" value="Generate Invoice &raquo;"></td></tr>
			</table></form>';
			return;
		}
		$billic->set_title('My Account');
		echo '<h1><i class="icon-user"></i> My Account</h1>';
		if (isset($_POST['update'])) {
			if (!empty($_POST['password']) && $_POST['password'] != 'Edit to Change Password') {
				$salt = $billic->rand_str(5);
				$password = md5($salt . $_POST['password']) . ':' . $salt;
				$db->q("UPDATE `users` SET `password` = ? WHERE `id` = ?", $password, $billic->user['id']);
				echo '<br><font color="green"><b>Password Changed!</b></font><br>';
			}
			if (!$billic->valid_email($_POST['email'])) {
				$billic->error('Email is invalid', 'email');
			}
			if (empty($billic->errors)) {
				$count = $db->q('SELECT COUNT(*) FROM `users` WHERE `email` = ? AND `id` != ?', $_POST['email'], $billic->user['id']);
				if ($count[0]['COUNT(*)'] > 0) {
					$billic->error('Email is already in use by another account', 'email');
				}
			}
			$_POST['api_ips'] = trim($_POST['api_ips']);
			$api_ips = explode(',', $_POST['api_ips']);
			foreach ($api_ips as $ip) {
				$ip = trim($ip);
				if (empty($ip)) {
					continue;
				}
				if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					$billic->errors[] = 'The API IP Address "' . safe($ip) . '" is invalid. Please enter a valid IP addresses separated by commas.';
				}
			}
			if (empty($billic->errors)) {
				$signature = strip_tags($_POST['signature'], $this->settings['allowed_tags']);
				$db->q('UPDATE `users` SET `email` = ?, `auto_renew` = ?, `tickets_open_secret` = ?, `signature` = ?, `api_ips` = ? WHERE `id` = ?', $_POST['email'], $_POST['auto_renew'], $_POST['tickets_open_secret'], $signature, $_POST['api_ips'], $billic->user['id']);
				$billic->user = $db->q('SELECT * FROM `users` WHERE `id` = ?', $billic->user['id']);
				$billic->user = $billic->user[0];
				echo '<b><font color="green">Successfully Updated!</font></b>';
			}
		}
		$billic->show_errors();
		echo '<form method="POST"><div id="myaccount_col"><div id="myaccount_col_padding">';
		echo '<table class="table table-striped"><tr><th colspan="2">Personal Information</th></tr>';
		echo '<tr><td>First Name</td><td><input type="text" class="form-control" name="firstname" value="' . safe($billic->user['firstname']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Last Name</td><td><input type="text" class="form-control" name="lastname" value="' . safe($billic->user['lastname']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Company Name</td><td><input type="text" class="form-control" name="companyname" value="' . safe($billic->user['companyname']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>VAT Number</td><td><input type="text" class="form-control" name="vatnumber" value="' . safe($billic->user['vatnumber']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Email</td><td><input type="text" class="form-control" name="email" value="' . safe($billic->user['email']) . '" style="width: 100%"></td></tr>';
		echo '<tr><td>Address 1</td><td><input type="text" class="form-control" name="address1" value="' . safe($billic->user['address1']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Address 2</td><td><input type="text" class="form-control" name="address2" value="' . safe($billic->user['address2']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>City</td><td><input type="text" class="form-control" name="city" value="' . safe($billic->user['city']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>State</td><td><input type="text" class="form-control" name="state" value="' . safe($billic->user['state']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Post Code</td><td><input type="text" class="form-control" name="postcode" value="' . safe($billic->user['postcode']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Country</td><td><input type="text" class="form-control" name="country" value="' . safe($billic->user['country']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Phone Number</td><td><input type="text" class="form-control" name="phonenumber" value="' . safe($billic->user['phonenumber']) . '" style="width: 100%" disabled></td></tr>';
		echo '<tr><td>Password</td><td><input type="text" class="form-control" name="password" value="Edit to Change Password" onblur="if(this.value==\'\') this.value=\'Edit to Change Password\';" onFocus="if(this.value==\'Edit to Change Password\') this.value=\'\';" style="width: 100%"></td></tr>';
		echo '</table>';
		echo '</div></div><div id="myaccount_col"><div id="myaccount_col_padding">';
		echo '<table class="table table-striped"><tr><th colspan="2">Account Info</th></tr>';
		echo '<tr><td width="120">Status</td><td>';
		switch ($billic->user['verified']) {
			case 0:
				echo 'Unverified';
			break;
			case 1:
				echo '<span class="green">Verified</span>';
			break;
			case 2:
				echo 'Normal';
			break;
		}
		echo '</td></tr>';
		echo '<tr><td style="vertical-align:middle">Credit</td><td><div class="col-sm-6"><div class="input-group"><span class="input-group-addon">' . get_config('billic_currency_prefix') . '</span><input type="text" class="form-control" value="' . safe($billic->user['credit']) . '" readonly><span class="input-group-addon">' . get_config('billic_currency_suffix') . '</span></div></div>';
		if (get_config('myaccount_addfunds_enable') == 1) {
			echo '<label class="control-label col-sm-6" style="text-align:center"><a href="/User/MyAccount/Action/AddFunds" class="btn btn-success"><i class="icon-money-banknote"></i> Add Funds</a></label>';
		}
		echo '</td></tr>';
		if ($billic->module_exists('DiscountTiers')) {
			$billic->module('DiscountTiers');
			echo '<tr><td>Discount Tier</td><td>' . $billic->modules['DiscountTiers']->calc_discount_tier($user) . '%</td></tr>';
		}
		echo '</table><br>';
		echo '<table class="table table-striped"><tr><th colspan="2">Additional Settings</th></tr>';
		echo '<tr><td>Auto Renew</td><td><input type="checkbox" name="auto_renew" value="1"' . ($billic->user['auto_renew'] == '1' ? ' checked' : '') . '> Automatically pay new invoices using your account credit.</td></tr>';
		echo '<tr><td width="120">New Ticket Passphrase</td><td><input type="text" class="form-control" name="tickets_open_secret" value="' . safe($billic->user['tickets_open_secret']) . '"><br>When opening a support ticket by email, if this passphrase is set you will need to enter somewhere in your email otherwise the new ticket will be rejected.</td></tr>';
		$billic->add_script('//cdn.ckeditor.com/4.5.9/basic/ckeditor.js');
		echo '<tr><td>Ticket Signature</td><td><textarea name="signature" style="width:100%;height: 75px" id="signature_body">' . safe($billic->user['signature']) . '</textarea></td></tr>';
		echo '</table><br>';
		echo '<script>addLoadEvent(function() {
	// Update message while typing (part 1)
	key_count_global = 0; // Global variable
	
	CKEDITOR.replace(\'signature_body\', {   
		allowedContent: true,
		enterMode: CKEDITOR.ENTER_BR,
		disableNativeSpellChecker: false,
	});
});</script>';
		if (empty($billic->user['api_key']) || isset($_POST['update_api_key'])) {
			$billic->user['api_key'] = $billic->rand_str(20);
			$db->q('UPDATE `users` SET `api_key` = ? WHERE `id` = ?', $billic->user['api_key'], $billic->user['id']);
		}
		echo '<table class="table table-striped"><tr><th colspan="2">API Access</th></tr>';
		echo '<tr><td width="120">API Key</td><td><div class="input-group"><input type="text" class="form-control" value="' . safe($billic->user['api_key']) . '" readonly> <div class="input-group-addon"><input type="checkbox" name="update_api_key"> Regenerate</div></div></td></tr>';
		echo '<tr><td>API IPs</td><td><input type="text" class="form-control" name="api_ips" value="' . safe(empty($_POST['api_ips']) ? $billic->user['api_ips'] : $_POST['api_ips']) . '" style="width: 100%"></td></tr>';
		echo '</table>';
		echo '</div></div>';
		echo '<div style="clear:both"></div>';
		$billic->module_call_functions('MyAccount_submodule');
		echo '<div align="center"><br><input type="submit" class="btn btn-success" name="update" value="Update My Account &raquo;"></div></form>';
		echo '<div style="clear:both"></div>';
		echo '<style>
#myaccount_col{
    width:50%;
    float:left;
}
#myaccount_col_padding{
	padding: 2px;
}
</style>';
	}
	function settings($array) {
		global $billic, $db;
		if (isset($_POST['update'])) {
			if (empty($billic->errors)) {
				set_config('myaccount_addfunds_enable', $_POST['myaccount_addfunds_enable']);
				set_config('myaccount_addfunds_min', $_POST['myaccount_addfunds_min']);
				set_config('myaccount_addfunds_max', $_POST['myaccount_addfunds_max']);
				set_config('myaccount_addfunds_tax_group', $_POST['myaccount_addfunds_tax_group']);
				$billic->status = 'updated';
			}
		} else {
			echo '<form method="POST"><input type="hidden" name="billic_ajax_module" value="MyAccount"><table class="table table-striped">';
			echo '<tr><th>Setting</th><th>Value</th></tr>';
			echo '<tr><td>Enable Add Funds</td><td><input type="checkbox" name="myaccount_addfunds_enable" value="1"' . (get_config('myaccount_addfunds_enable') == 1 ? ' checked' : '') . '></td></tr>';
			echo '<tr><td>Add Funds Minimum</td><td><input type="text" class="form-control" name="myaccount_addfunds_min" value="' . safe(get_config('myaccount_addfunds_min')) . '" style="clear:none"></td></tr>';
			echo '<tr><td>Add Funds Maximum</td><td><input type="text" class="form-control" name="myaccount_addfunds_max" value="' . safe(get_config('myaccount_addfunds_max')) . '"></td></tr>';
			echo '<tr><td>Add Funds Tax Group</td><td><select class="form-control" name="myaccount_addfunds_tax_group">';
			$tax_groups = $db->q('SELECT * FROM `tax_groups` ORDER BY `name` ASC');
			echo '<option value="None">None</option>';
			foreach ($tax_groups as $group) {
				echo '<option value="' . safe($group['name']) . '"' . (get_config('myaccount_addfunds_tax_group') == $group['name'] ? ' selected' : '') . '>' . safe($group['name']) . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><td colspan="2" align="center"><input type="submit" class="btn btn-default" name="update" value="Update &raquo;"></td></tr>';
			echo '</table></form>';
		}
	}
	function api() {
		global $billic, $db;
		$billic->force_login();
		switch ($_GET['action']) {
			case 'credit':
				die(json_encode(array(
					'credit' => $billic->user['credit']
				)));
			break;
			default:
				die(json_encode(array(
					'error' => 'Module action is required'
				)));
			break;
		}
	}
}
