<?php
/**
 * Extension Import function. You will need to modify this function slightly ensure all values are added to the database.
 * Please see the section below on how to do this.
 */

function bebop_twitter_import( $extension ) {
	global $bp, $wpdb;
	if ( empty( $extension ) ) {
		bebop_tables::log_general( 'Importer', 'The $extension parameter is empty..' );
		return false;
	}
	else if ( ! bebop_tables::check_option_exists( 'bebop_' . $extension . '_consumer_key' ) ){
		bebop_tables::log_error( 'Importer', 'No consumer key was found for ' . $extension );
		return false;
	}
	else {
		$this_extension = bebop_extensions::get_extension_config_by_name( $extension );
	}
	
	//item counter for in the logs
	$itemCounter = 0;
	if ( bebop_tables::check_option_exists( 'bebop_' . $this_extension['name'] . '_consumer_key' ) ) {
		$user_metas = bebop_tables::get_user_ids_from_meta_name( 'bebop_' . $this_extension['name'] . '_oauth_token' );
		if ( $user_metas ) {
			foreach ( $user_metas as $user_meta ) {
				//Ensure the user is currently wanting to import items.
				if ( bebop_tables::get_user_meta_value( $user_meta->user_id, 'bebop_' . $this_extension['name'] . '_active_for_user' ) == 1 ) {
					//check for daylimit
					if ( ! bebop_filters::import_limit_reached( $this_extension['name'], $user_meta->user_id ) ) {
						//Handle the OAuth requests
						$OAuth = new bebop_oauth();
						$OAuth->set_callback_url( $bp->root_domain );
						$OAuth->set_consumer_key( bebop_tables::get_option_value( 'bebop_' . $this_extension['name'] . '_consumer_key' ) );
						$OAuth->set_consumer_secret( bebop_tables::get_option_value( 'bebop_' . $this_extension ['name']. '_consumer_secret' ) );
						$OAuth->set_access_token( bebop_tables::get_user_meta_value( $user_meta->user_id, 'bebop_' . $this_extension['name'] . '_oauth_token' ) );
						$OAuth->set_access_token_secret( bebop_tables::get_user_meta_value( $user_meta->user_id, 'bebop_' . $this_extension['name'] . '_oauth_token_secret' ) );
						
						$items = $OAuth->oauth_request( $this_extension['data_api'] );
						$items = simplexml_load_string( $items );
						
						/* 
						 * ******************************************************************************************************************
						 * We can get as far as loading the items, but you will need to adjust the values of 'bebop_create_buffer_item'		*
						 * to match the values from the extension's API.																	*
						 * This is because each API return data under different ways, and the simplest way to get around this is to have 	*
						 * the user modify the values. 																						*
						 * ******************************************************************************************************************
						 * 
						 * Values you will need to check and update are:
						 * 		$errors 						- Must point to the error boolean value (true/false)
						 * 		$username						- Must point the the value holding the username of the person.
						 *.		$item_id						- Should be the ID of the item returned through the data API.
						 * 		$item_content					- The actual content of the imported item.
						 * 		$item_published					- The time the item was published.
						 * 		$action_link					- This is where the link will point to - i.e. where the user can click to get more info.
						 */
						
						
						
						//Edit the following two variables to point to where the relevant content is being stored:
						$errors		 = $items->error;
						$username	 = '' . $items->status->user->screen_name[0];
						
						
						
						if ( $items && ! $errors ) {
							bebop_tables::update_user_meta( $user_meta->user_id, $this_extension['name'], 'bebop_' . $this_extension['name'] . '_username', $username );
							foreach ( $items as $item ) {
								
								
								
								//Edit the following three variables to point to where the relevant content is being stored:
								$item_id			= $item->id;
								$item_content		= $item->text;
								$item_published		= $item->created_at;
								$action_link 		= 'http://www.twitter.com/' . $username . '/status/' . $item_id;
								//Stop editing - you should be all done.
								
								
								
								
								if ( ! bebop_filters::import_limit_reached( $this_extension['name'], $user_meta->user_id ) ) {
									$activity_info = bp_activity_get( array( 'filter' => array( 'secondary_id' => $user_meta->user_id . '_' . $item_id ), 'show_hidden' => true ) );
									
									if ( ( empty( $activity_info['activities'][0] ) ) && ( ! bp_activity_check_exists_by_content( $item_content ) ) ) {
										if ( bebop_create_buffer_item(
														array(
															'user_id'			=> $user_meta->user_id,
															'extention'			=> $this_extension['name'],
															'type'				=> $this_extension['content_type'],
															'content'			=> $item_content,
															'content_oembed'	=> $this_extension['content_oembed'],
															'item_id'			=> $item_id,
															'raw_date'			=> gmdate( 'Y-m-d H:i:s', strtotime( $item_published ) ),
															'actionlink'		=> $action_link,
														)
										) ) {
											$itemCounter++;
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	//return the result
	return $itemCounter . ' tweets';
}