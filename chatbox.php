<?php
/*
Plugin Name: Chatbox
Plugin URI: http://nutomic.bplaced.net/chatbox
Description: A plugin that places a simple chatbox in your sidebar.
Version: 1.1
Author: Felix Ableitner
Author URI: http://nutomic.bplaced.net
License: GPL2


    Copyright 2010  Felix Ableitner  (email : felix.ableitner@web.de)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/
class chatbox extends WP_Widget {

    function chatbox() { // widget actual processes
        parent::WP_Widget(false, $name = 'Chatbox');	
    }

    function widget($args, $instance) {	// outputs the content of the widget

		$oldpost = '';
		$banned = explode ( ' ', esc_attr ( $instance['chatbox_banned'] ) );
		$banned_contained = '';
		global $wpdb;
		$table_name = $wpdb->prefix . "chatbox";
		
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			//create the database if it doeasnt exist
      
			$sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
					`id` SMALLINT NOT NULL,
					`userid` SMALLINT NOT NULL,
					`postcontent` TEXT NOT NULL
					);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			for ($i=0;$i<=$instance['chatbox_count']-1;$i++) {
				$wpdb->insert( $table_name, array( 'userid' => -1, 'postcontent' => '', 'id' => $i) );
			}
		}

		if ( (isset($_POST['chatbox_newpost']) ) and ( get_current_user_id ( ) != 0 ) and ( trim ( esc_attr ( $_POST ['chatbox_newpost'] ) ) != '' ) ) { //save new post to database
			$newpost = trim(esc_attr($_POST['chatbox_newpost']));
			for ($i=0;$i<count($banned);$i++) {
				if ( strpos ( $newpost, trim ( $banned[$i] ) ) ) $banned_contained = $banned_contained . trim ( $banned[$i] ) . ', ';
			}
			
			$banned_contained = substr ( $banned_contained, 0, count ( $banned_contained ) -1 );
			
			if ( strlen ($newpost) <= $instance['chatbox_length'] and $banned_contained == '' ) {
				
				for ( $i=$instance['chatbox_count']-1; $i>=1; $i-- ) {
					$j = $i - 1;
					$posts = $wpdb->get_row("SELECT * FROM `$table_name` WHERE `id` = $j", ARRAY_A );
					$wpdb->update( $table_name, array( 'userid' => $posts['userid'], 'postcontent' => $posts['postcontent'] ), array( 'id' => $i ), $format = null, array( '%d', '%d', '%s' ) );
				}
			
				$done = 0;
				$offset = 0;
				$i = 0;
				$link = '';
				$newpost = $_POST['chatbox_newpost'] . " ";
				while ( ! ($done) and ( $offset < strlen ( $newpost ) ) ) {//make links clickable
					$linkstart = strpos ( $newpost, "http://", $offset );
					if ( $linkstart !== false ) {
						$linkend = strpos ( $newpost, " ", $linkstart );
						$link = substr ( $newpost, $linkstart, ( $linkend - $linkstart ) );
						$newpost = str_replace ( $link, "<a href='$link'>$link</a>", $newpost );
						$offset += $linkstart + 15 + strlen ( $link ) * 2;
						$link = '';
						$i++;
					}
					else $done = 1;
				}
				$wpdb->update( $table_name, array( 'userid' => get_current_user_id ( ), 'postcontent' => $newpost), array( 'id' => 0 ), $format = null, array( '%d', '%d', '%s' ) );
			}
			else $oldpost = $newpost; //post invalid
		}
		
		if ( ( isset ( $_GET['chatbox_delete'] ) ) and ( get_current_user_id ( ) == 1 ) ) {
			for ( $i=$_GET['chatbox_delete']; $i<=$instance['chatbox_count']-1; $i++ ) {
				$j = $i + 1;
				$posts = $wpdb->get_row("SELECT * FROM `$table_name` WHERE `id` = $j", ARRAY_A );
				$wpdb->update( $table_name, array( 'userid' => $posts['userid'], 'postcontent' => $posts['postcontent']), array( 'id' => $i ), $format = null, array( '%d', '%d', '%s' ) );
			}
			$wpdb->update( $table_name, array( 'userid' => -1, 'postcontent' => ''), array( 'id' => $instance['chatbox_count']-1 ), $format = null, array( '%d', '%d', '%s' ) );
			$post_deleted = 1;
		}
		
		echo "<li id='chatbox-2' class='widget-container widget-chatbox' style='word-wrap:break-word'> \n 
				<h3 class='widget-title'>Chatbox</h3> \n
					<div class='chatbox-posts'> \n"; //echo widget title and div
		$userid = get_current_user_id ( );	
		
		for ( $i=0; $i<=$instance['chatbox_count']-1; $i++) { //echo all posts...
			$user = $wpdb->get_var("SELECT userid FROM $table_name WHERE id = $i");
			$post = $wpdb->get_var("SELECT postcontent FROM $table_name WHERE id = $i");
			
			if ( $userid == 1 ) $post = $post . " <a href='" . get_bloginfo( url ) . "?chatbox_delete=$i' title='Delete this post' style='color:#ff0000'>[x]</a>";
			if ( $user != -1 ) { //...unless one doesnt yet exist
				$userdata = get_userdata ( $user );
				echo "<div class='chatbox_single'> \n
						<span class='chatbox-name'><b>" . $userdata->display_name . "</b></span>"; //echo the user name
				echo "<span class='chatbox-post'>: $post</span>
									</div>"; //echo the post
			}
			elseif ($i == 0) {
				echo "No posts found.";
				break;
			}
		}
		
		echo "	</div> \n";
		if ($userid == 0) echo "<a href='./wp-login.php'>Login</a> or <a href='./wp-login.php'>register</a> to post. \n"; //sorry, you are not logged in
		
		else {
			echo   "<form id='chatbox' action='./' method='post'>\n
						<input type='text' name='chatbox_newpost' value='$oldpost'> \n
						<input type='submit' name='go' value='Post'> \n
					</form> \n"; //echo the form
		}
		if ( $post_deleted ) echo "Post successfully deleted. <br> \n";
		if ($oldpost != '') {
			if ( $banned_contained != '' ) echo "The following words are not allowed for posting: $banned_contained. Please remove them and post again. <br> \n"; 
			else echo "Your post is too long. Maximum length is " . esc_attr($instance['chatbox_length']) . " characters. <br> \n";
		}
		echo "</li> \n";
		
    }
	
    function update($new_instance, $old_instance) {	// processes widget options to be saved
		global $wpdb;
		if ($new_instance['chatbox_count'] > $old_instance['chatbox_count']) {
			for ($i=$old_instance['chatbox_count']+1; $i<=$new_instance['chatbox_count']-1; $i++) {
				$wpdb->insert( $table_name, array('id' => $i, 'username' => '', 'postcontent' => '' ));
			}
		}
		elseif ($new_instance['chatbox_count']-1 < $old_instance['chatbox_count']) {
			for ($i=$new_instance['chatbox_count']; $i<=$old_instance['chatbox_count']-1; $i++) {
				$wpdb->query("DELETE FROM $table_name WHERE id = '$i'");  
			}
		}
		return $new_instance;
    }

    function form($instance) { // outputs the options form on admin		
        ?>
            <p>
				<label for="<?php echo $this->get_field_id('chatbox_count'); ?>">
					<?php _e('Number of messages to display:'); ?> 
					<input class="widefat" id="<?php echo $this->get_field_id('chatbox_count'); ?>" name="<?php echo $this->get_field_name('chatbox_count'); ?>" type="text" value="<?php echo esc_attr(  $instance['chatbox_count'] ); ?>" />
				</label>
				<label for="<?php echo $this->get_field_id('chatbox_length'); ?>">
					<?php _e('Maximum message length in characters:'); ?> 
					<input class="widefat" id="<?php echo $this->get_field_id('chatbox_length'); ?>" name="<?php echo $this->get_field_name('chatbox_length'); ?>" type="text" value="<?php echo esc_attr ( $instance['chatbox_length'] ); ?>" />
				</label>
				<label for="<?php echo $this->get_field_id('chatbox_banned'); ?>">
					<?php _e('Banned words (space seperated):'); ?> 
					<input class="widefat" id="<?php echo $this->get_field_id('chatbox_banned'); ?>" name="<?php echo $this->get_field_name('chatbox_banned'); ?>" type="text" value="<?php echo esc_attr ( $instance['chatbox_banned'] ); ?>" />
				</label>
        <?php 
    }

} // class Chatbox
add_action('widgets_init', create_function('', 'return register_widget("Chatbox");'));



?>
