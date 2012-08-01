<?php
/**
 * Import starter
 */

function bebop_youtube_start_import() {
	$importer = new bebop_youtube_import();
	return $importer->do_import();
}

/**
 * Youtube Import Class
 */

class bebop_youtube_import {
	
	public function do_import() {
		global $bp, $wpdb;
		
		require_once (ABSPATH . WPINC . '/class-feed.php');
		
		$itemCounter = 0;
		
		$user_metas = bebop_tables::get_user_ids_from_meta_name( 'bebop_youtube_username' );
		
		if ( $user_metas ) {
			foreach ( $user_metas as $user_meta ) {
				//Ensure the user is wanting to import items.
				if ( bebop_tables::check_user_meta_exists( $user_meta->user_id, 'bebop_youtube_username' )  && bebop_tables::get_user_meta_value( $user_meta->user_id, 'bebop_youtube_active_for_user' ) ) {
					//get these urls for import
					$importUrls = 'http://gdata.youtube.com/feeds/api/users/' . bebop_tables::get_user_meta_value( $user_meta->user_id, 'bebop_youtube_username' ) . '/uploads';
					$items = null;
					$feed = new SimplePie();
					$feed->set_feed_url( $importUrls );
					$feed->set_cache_class( 'WP_Feed_Cache' );
					$feed->set_file_class( 'WP_SimplePie_File' );
					$feed->enable_cache( false );
					$feed->set_cache_duration( 0 );
					do_action_ref_array( 'wp_feed_options', array( $feed, $importUrl ) );
					$feed->init();
					$feed->handle_content_type();
					
					if ( ! $feed->error ) {
						$items = $feed->get_items();
					}
					else {
						bebop_tables::log_error( 'bebop_youtube_import', 'feed error: ' . $feed->error );
					}
					
					if ( $items ) {
						foreach ( $items as $item ) {
							if ( ! bebop_filters::import_limit_reached( 'youtube', $user_meta->user_id ) ) {
								// get video player URL
								$link = $item->get_permalink();
								
								//get the video id from player url
								$videoIdArray = explode( '=', $link );
								$videoId = $videoIdArray[1];
								$videoId = str_replace( '&feature', '', $videoId );
								$videoId = str_replace( '&amp;feature', '', $videoId );
								//get the thumbnail
								// $thumbnail = "http://i.ytimg.com/vi/" . $videoId . "/0.jpg";
								$activity_info = bp_activity_get( array( 'filter' => array( 'secondary_id' => $user_meta->user_id . '_' . $videoId ), 'show_hidden' => true, ) );
								if ( ! $activity_info['activities'][0]->id ) {
									$description = '';
									$description = $item->get_content();
									if ( strlen( $description ) > 200 ) {
										$description = substr( $description, 0, 200 ) . "... <a href='http://www.youtube.com/watch/?v=" . $videoId . "'>read more</a>";
									}
									
									//This manually puts the link and description together with a line break.
									$content = 'http://www.youtube.com/watch?v=' . $videoId . '
									Description: ' . $description;
									
									//pre convert date
									$ts = strtotime( $item->get_date() );
									
									$returnCreate = bebop_create_buffer_item(
													array(
														'user_id' 			=> $user_meta->user_id,
														'extention' 		=> 'youtube',
														'type' 				=> 'Youtube video',
														'content' 			=> $content,
														'content_oembed' 	=> true,		//true if you want to use oembed, false if not.
														'item_id' 			=> $videoId,
														'raw_date' 			=> date( 'Y-m-d H:i:s', $ts ),
														'actionlink'	 	=> 'http://www.youtube.com/' . bebop_tables::get_user_meta_value( $user_meta->user_id, 'bebop_youtube_username' ),
													)
									);
									
									if ( $returnCreate ) {
										$itemCounter++;
									}
								}
							}
						}
					}
				}
			}
		}
		//return the result
		return $itemCounter . ' youtube video\'s';
	}
}