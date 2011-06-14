<?php
/*
Plugin Name: Create Items & Taxonomies
Plugin URI: http://github.com/avaly/create-pages
Description: Create pages, posts, custom post items & taxonomies automatically
Version: 1.2.1
Author: Valentin Agachi
Author URI: http://agachi.name
Contributors: Valentin Ceaprazaru
License: GPL2
*/



function cp_plugin_menu()
{
	add_submenu_page('tools.php', 'Create Items', 'Create Items', 'manage_options', 'cp-create', 'cp_create');
	add_submenu_page('tools.php', 'Create Terms', 'Create Terms', 'manage_options', 'ct-create', 'ct_create');
}
add_action('admin_menu', 'cp_plugin_menu');



function cp_plugin_action_links($data)
{
	$data[] = '<b><a href="tools.php?page=cp-create">Create Items</a></b>';
//	$data[] = '<b><a href="tools.php?page=cc-create">Create Categories</a></b>';
	return $data;
}
add_filter('plugin_action_links_'.basename(__FILE__), 'cp_plugin_action_links');



function cp_create()
{
	if (!current_user_can('manage_options')) {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	$res = cp_perform();

	$post_types = array('page', 'post') + get_post_types(array('_builtin' => false));

?>
	<div class="wrap">

		<h2>Create Pages, Posts or Custom post items</h2>

<?php

		if ($res && is_array($res))
		{
			echo '<div id="message" class="updated below-h2">';
				echo '<p>Removed <b>'.$res['deleted'].'</b> old items</p>';
				echo '<p>Created <b>'.$res['created'].'</b> new items</p>';
			echo '</div>';
		}

?>

		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
			<table cellspacing="0" class="form-table">
			<tbody>
				<tr>
					<th><label for="type">Item type</label></th>
					<td><select name="type" id="type">
<?php
						foreach ($post_types as $type)
						{
							echo '<option value="'.$type.'">'.$type.'</option>';
						}
?>
					</select></td>
				</tr>
				<tr>
					<th>Clean before creating</th>
					<td><label><input type="checkbox" name="clean" value="1" id="clean"/> Remove all items before creating new items</label></td>
				</tr>
				<tr>
					<th><label for="parent">Parent</label></th>
					<td><input type="text" name="parent" id="parent" value="0" size="5"/></td>
				</tr>
				<tr>
					<th><label for="menu_order">Menu_order start</label></th>
					<td><input type="text" name="menu_order" id="menu_order" value="1" size="5"/></td>
				</tr>
				<tr>
					<th>Insert dummy content</th>
					<td><label><input type="checkbox" name="dummy" value="1" checked="checked" id="dummy"/> Insert dummy content for all created items</label></td>
				</tr>
				<tr>
					<th><label for="items">List of items to create</label></th>
					<td>
						<textarea name="items" id="items" rows="20" cols="60"></textarea>
						<p class="description">List items, each line containing an items with the following options:<br/>
						If line is prefixed by any number of TAB or - (minus) characters, it will indent its level (setting it as a child items of the previous items).<br/>
						If line is suffixed by a JSON object, those fields will be set as the custom fields of the item.<br/>
						The menu_order field will be incremented on each level.<br/>
						<br/>
						Examples:</p>
						<pre>Item #1
Item #2
	Item #2.1
	Item #2.2
Item #3
Item #4 {"key1":"value1","key2":"value2"}</pre>
					</td>
				</tr>
			</tbody>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Create Post Items"/></p>
		</form>

		<p class="created" style="text-align:right"><em>Created by <a href="http://agachi.name/">Valentin Agachi</a></em></p>

	</div>
<?php
}



function cp_perform()
{
	global $user_ID, $dummyContent;

	if (!is_array($_POST) || !count($_POST))
		return false;

	$result = array('deleted' => 0, 'created' => 0, 'errors' => array());

	if ($_POST['clean'])
	{
		$items = get_posts(array('post_type' => $_POST['type'], 'numberposts' => -1));

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

	$parents = array($_POST['parent']);
	$orders = array($_POST['menu_order']);
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
		$order = $orders[$level]++;

		$params = array(
			'menu_order' => $order,
			'ping_status' => 'closed',
			'post_author' => $user_ID,
			'post_type' => $_POST['type'],
			'post_status' => 'publish',
			'post_parent' => $parent,
			'post_date' => date('Y-m-d H:i:s'),
			'post_title' => trim($match[2]),
		);
		if ($_POST['dummy'])
		{
			$params['post_content'] = $dummyContent;
		}
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



$dummyContent = <<<ELOREM

<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris congue euismod sem, eu congue odio pellentesque egestas. Nam vitae consectetur ante. Morbi et enim non est facilisis dignissim. Aenean vel dui tortor, in facilisis lorem. Phasellus neque neque, semper congue iaculis eu, ullamcorper et leo. Sed rhoncus posuere dui. Fusce in purus metus. Nullam fringilla metus quis magna tristique aliquet. <a href="#">Phasellus euismod</a> nunc dignissim mi eleifend ac bibendum ante tincidunt. Aenean quam purus, commodo vitae dapibus et, adipiscing in libero. Quisque sapien mi, faucibus at consequat vitae, auctor accumsan lacus. Donec et elit dui, in bibendum ipsum. Nunc libero est, egestas sit amet condimentum pulvinar, mattis quis enim.</p>
<ul>
	<li>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</li>
	<li><a href="#">Phasellus</a> id arcu quis odio egestas semper.</li>
	<li>Nulla ac nibh ac risus viverra facilisis.</li>
</ul>
<p>In pulvinar ligula eu libero tempus facilisis sit amet adipiscing magna. Fusce interdum, nibh non accumsan tristique, nisi lectus feugiat orci, sit amet tempus lacus justo mattis libero. Aliquam ut diam ac turpis faucibus pretium non in est. Phasellus libero nunc, ullamcorper sed tincidunt ut, bibendum sit amet purus. Pellentesque tempus urna eu nibh ornare dignissim vel eu turpis. Aliquam a nibh sit amet sem feugiat porta eu at magna. Morbi auctor felis id dui rhoncus non commodo turpis commodo. Phasellus sed neque erat, eget bibendum nibh. Phasellus vestibulum fringilla arcu vel adipiscing. Suspendisse euismod sollicitudin erat eu adipiscing. Nullam dolor nunc, aliquet vitae aliquam a, lacinia nec lorem. Nullam at magna ipsum, id accumsan libero. Nunc arcu metus, elementum vitae tempor nec, vulputate eu ligula.</p>
<ol>
	<li>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</li>
	<li><a href="#">Phasellus </a>id arcu quis odio egestas semper.</li>
	<li>Nulla ac nibh ac risus viverra facilisis.</li>
</ol>
<p>Morbi imperdiet suscipit orci, non gravida lorem aliquam id. Suspendisse id eros eu neque molestie adipiscing sed vel augue. Etiam id diam nisl. Suspendisse elementum dapibus massa, eget vulputate velit elementum et. Vivamus metus nisi, mattis vel elementum ac, volutpat in mi. Sed sagittis nulla quis velit viverra imperdiet. Suspendisse in mi nisi. Mauris nisi massa, ullamcorper eu gravida vitae, imperdiet nec felis. Sed vitae lorem urna. Nunc at placerat turpis. Etiam porttitor mi convallis diam euismod auctor.</p>

ELOREM;






/*
 *	Create Terms
 *
 */


function ct_create()
{
	if (!current_user_can('manage_options')) {
		wp_die( __('You do not have sufficient permissions to access this category.') );
	}

	$res = ct_perform();

	$taxonomies = array('category', 'post_tag') + get_taxonomies(array('_builtin' => false));

?>
	<div class="wrap">

		<h2>Create Terms</h2>

<?php

		if ($res && is_array($res))
		{
			echo '<div id="message" class="updated below-h2">';
				echo '<p>Removed <b>'.$res['deleted'].'</b> old terms</p>';
				echo '<p>Created <b>'.$res['created'].'</b> new terms</p>';
			echo '</div>';
		}

?>

		<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
			<table cellspacing="0" class="form-table">
			<tbody>
				<tr>
					<th><label for="tax">Item type</label></th>
					<td><select name="tax" id="tax">
<?php
						foreach ($taxonomies as $tax)
						{
							echo '<option value="'.$tax.'">'.$tax.'</option>';
						}
?>
					</select></td>
				</tr>
				<tr>
					<th>Clean before creating</th>
					<td><label><input type="checkbox" name="clean" value="1" id="clean"/> Remove all terms before creating new terms</label></td>
				</tr>
				<tr>
					<th>Insert dummy description</th>
					<td><label><input type="checkbox" name="dummy" value="1" checked="checked" id="dummy"/> Insert dummy description for all created terms</label></td>
				</tr>
				<tr>
					<th><label for="items">List of terms to create</label></th>
					<td>
						<textarea name="items" id="items" rows="20" cols="60"></textarea>
						<p class="description">List terms, each line containing a term with the following options:<br/>
						If line is prefixed by any number of TAB or - (minus) characters, it will indent its level (setting it as a child term of the previous term).<br/>
						<br/>
						Examples:</p>
						<pre>term #1
term #2
	term #2.1
	term #2.2
term #3
term #4</pre>
					</td>
				</tr>
			</tbody>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Create terms"/></p>
		</form>

		<p class="created" style="text-align:right"><em>Created by <a href="http://agachi.name/">Valentin Agachi</a></em></p>

	</div>
<?php
}



function ct_perform()
{
	global $user_ID, $dummyDescription;

	if (!is_array($_POST) || !count($_POST))
		return false;

	$result = array('deleted' => 0, 'created' => 0, 'errors' => array());
	
	if ($_POST['clean'])
	{
		$items = get_terms($_POST['tax'], array('hide_empty' => false));

		foreach ($items as $item)
		{
			if ($item->term_id == 1)
				continue;
			$res = wp_delete_term($item->term_id, $_POST['tax']);
			if ($res)
			{
				$result['deleted']++;
			}
		}
	}

	$_POST['items'] = stripslashes($_POST['items']);
	$items = explode("\n", str_replace("\r", '', $_POST['items']));

	$parents = array(0);
	$level = 0;
	$last = 0;

	foreach ($items as $item)
	{
		if (!preg_match('~^([\t\-]*)([^\{]+)$~i', $item, $match))
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

		$params = array(
			'parent' => $parent,
			'slug' => $match[2],
		);
		if ($_POST['dummy'])
		{
			$params['description'] = $dummyDescription;
		}
		$last = wp_insert_term($match[2], $_POST['tax'], $params);

		if ($last)
		{
			$last = $last['term_id'];
			$result['created']++;
		}		
	}

	return $result;
}

$dummyDescription = <<<ELOREM

Morbi imperdiet suscipit orci, non gravida lorem aliquam id. Suspendisse id eros eu neque molestie adipiscing sed vel augue. Etiam id diam nisl. Suspendisse elementum dapibus massa, eget vulputate velit elementum et. 

ELOREM;




// no php end tag