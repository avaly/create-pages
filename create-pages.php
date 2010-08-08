<?php
/*
Plugin Name: Create Pages
Plugin URI: http://github.com/avaly/create-pages
Description: Create pages automatically
Version: 1.0
Author: Valentin Agachi
Author URI: http://agachi.name
License: GPL2
*/



function cp_plugin_menu()
{
	add_submenu_page('tools.php', 'Create Pages', 'Create Pages', 'manage_options', 'cp-create', 'cp_create');
}
add_action('admin_menu', 'cp_plugin_menu');



function cp_plugin_action_links($data)
{
	$data[] = '<b><a href="tools.php?page=cp-create">Create</a></b>';
	return $data;
}
add_filter('plugin_action_links_'.basename(__FILE__), 'cp_plugin_action_links');


function cp_create()
{
	if (!current_user_can('manage_options')) {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	$res = cp_perform();

?>
	<div class="wrap">

		<h2>Create Pages</h2>

<?php

		if ($res && is_array($res))
		{
			echo '<div id="message" class="updated below-h2">';
				echo '<p>Removed <b>'.$res['deleted'].'</b> old pages</p>';
				echo '<p>Created <b>'.$res['created'].'</b> new pages</p>';
			echo '</div>';
		}

?>

		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
			<table cellspacing="0" class="form-table">
			<tbody>
				<tr>
					<th>Clean before creating</th>
					<td><label><input type="checkbox" name="clean" value="1" id="clean"/> Remove all pages before creating new pages</label></td>
				</tr>
				<tr>
					<th>List of pages to create</th>
					<td>
						<textarea name="items" id="items" rows="20" cols="60"></textarea>
						<p class="description">List pages, each line containing a page with the following options:<br/>
						If line is prefixed by any number of TAB or - (minus) characters, it will indent its level (setting it as a child page of the previous page).<br/>
						If line is suffixed by a JSON object, those items will be set as the custom fields of the page.<br/>
						The menu_order field will be incremented on each level.<br/>
						<br/>
						Examples:</p>
						<pre>Page #1
Page #2
	Page #2.1
	Page #2.2
Page #3
Page #4 {"key1":"value1","key2":"value2"}</pre>
					</td>
				</tr>
			</tbody>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Create Pages"/></p>
		</form>

	</div>
<?php
}



function cp_perform()
{
	global $user_ID;

	if (!is_array($_POST) || !count($_POST))
		return false;

	$result = array('deleted' => 0, 'created' => 0, 'errors' => array());

	if ($_POST['clean'])
	{
		$items = get_posts(array('post_type' => 'page', 'numberposts' => 500));

		foreach ($items as $item)
		{
			$res = wp_delete_post($item->ID);
			if ($res)
			{
				$result['deleted']++;
			}
		}
	}

	$_POST['items'] = stripslashes($_POST['items']);
	$items = explode("\n", str_replace("\r", '', $_POST['items']));

	$parents = array(0);
	$orders = array();
	$level = 0;
	$last = 0;

	foreach ($items as $item)
	{
		if (!preg_match('~^([\t\-]*)([^\{]+)(\s*\{[^\}]+\})?$~i', $item, $match))
		{
			$result['errors'][] = $item;
			continue;
		}

		$l = strlen($match[1]);
		$parent = 0;

		// new child
		if ($level < $l)
		{
			$level++;
			$parent = $parents[$level] = $last;
			$orders[$level] = 0;
		}
		// child on the same level
		elseif ($level == $l)
		{
			$parent = $parents[$level];
		}
		// new sibling
		elseif ($level > $l)
		{
			$level--;
			$parent = $parents[$level];
		}
		$order = ++$orders[$level];

		$params = array(
			'menu_order' => $order,
			'ping_status' => 'closed',
			'post_author' => $user_ID,
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_parent' => $parent,
			'post_date' => date('Y-m-d H:i:s'),
			'post_title' => $match[2],
		);
		$last = wp_insert_post($params);
		
		// post inserted
		if ($last)
		{
			$result['created']++;

			// create custom fields
			if ($match[3])
			{
				$fields = json_decode($match[3], true);
				if (is_array($fields))
					foreach ($fields as $k => $v)
					{
						add_post_meta($last, $k, $v);
					}
			}
		}
	}

	return $result;
}


