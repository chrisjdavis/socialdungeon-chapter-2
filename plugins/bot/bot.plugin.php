<?php
namespace Habari;

class Bot extends Plugin
{
	const TWITKEY = '';
	const TWITSECRET = '';
	const OAUTH_TOKEN = '';
	const OAUTH_SECRET = '';

  /**
	 * insert
	 *
	 * @access private
	 * @param mixed array $data
	 * @param string $table_name
	 * @return void
	 */
	private function insert(array $data, string $table_name) {
		return DB::insert( DB::table( $table_name ), $data );
	}

	public function filter_autoload_dirs($dirs) {
		$dirs[] = __DIR__ . '/classes';

		return $dirs;
	}

	public function action_init() {
		DB::register_table( 'person_stats' );
	}

  /**
	 * setup_stats_table function.
	 *
	 * @access private
	 * @return void
	 */
	private function setup_stats_table() {
		$sql = "CREATE TABLE {\$prefix}person_stats (
		  id int(11) unsigned NOT NULL AUTO_INCREMENT,
			user_id int(11) unsigned DEFAULT NULL,
		  updated varchar(255) CHARACTER SET latin1 DEFAULT NULL,
			awarded_by varchar(255) CHARACTER SET latin1 DEFAULT NULL,
		  PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

		DB::dbdelta( $sql );
	}

	private function setup_character_class_table() {
	  $sql = "CREATE TABLE {\$prefix}character_classes (
	    id int(11) unsigned NOT NULL AUTO_INCREMENT,
	    title varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
	    slug varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
	    description text COLLATE utf8mb4_general_ci,
	    PRIMARY KEY (`id`)
	  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

	  DB::dbdelta( $sql );
}

public function setup_character_alignment_table() {
  $sql = "CREATE TABLE {\$prefix}character_alignments (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    abbr varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    slug varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    description text COLLATE utf8mb4_general_ci,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

  DB::dbdelta( $sql );
}

public function setup_weapons_table() {
  $sql = "CREATE TABLE {\$prefix}weapons (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
    title varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    damage varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    unique int(11) unsigned DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

  DB::delta( $sql );
}

  public function action_plugin_activated( string $plugin_file ) {
		$this->setup_stats_table();
	}

  public function filter_default_rewrite_rules(array $rules) {
    $this->add_rule('"roll"/"mentions"', 'display_get_mentions');

		return $rules;
	}

  private function exists(int $id, string $table) {
		$check = DB::get_column( "select id from $table where twitter_id = :id", array('id' => $id) );

		if( $check ) {
			return true;
		} else {
			return false;
		}
	}

	private function save_player(string $handle) {
		$boss = false;
		$connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );
		$details = $connection->get( 'users/show', ["screen_name" => $handle] );

		$class = DB::get_row( 'select id from {character_classes} order by rand() limit 1' );
		$alignment = DB::get_row( 'select id from {character_alignments} order by rand() limit 1' );
		$weapon = DB::get_row( 'select id from {weapons} where class_id = :cid', array('cid' => $class->id) );
		$kingdom = $this->process_location( $details->location );

		$attack = $details->followers_count;
		$defense = $details->friends_count;

		if( $details->verified == true ) {
			$boss = true;
		}

		$args = array(
			'twitter_id'		=>	$details->id,
			'name'					=>	$details->name,
			'handle'				=>	$details->screen_name,
			'description'		=>	$details->description,
			'avatar'				=> 	$details->profile_image_url_https,
			'class_id'			=>	$class->id,
			'alignment_id'	=>	$alignment->id,
			'weapon_id'			=>	$weapon->id,
			'kingdom_id'		=>	$kingdom,
			'defense_points'	=>	$defense,
			'attack_points'		=>	$attack,
			'boss'						=>	$boss,
		);

		if( $this->exists( $details->id, '{account_details}' ) == false ) {
			if( !empty($args) ) {
				$this->insert( $args, '{account_details}' );
			}
		} else {
			DB::query( 'update {account_details} set avatar = :avatr where twitter_id = :id', array('avatr' => $details->profile_image_url_https, 'id' => $details->id) );
		}
	}

	private function announce(array $args) {
		$announcement = '@' . $args['awarded_by'] . ' awarded ' . $args['points'] . ' ' . $args['category'] . ' points to @' . $args['awarded_to'];
		$connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );

		$statues = $connection->post( 'statuses/update', ['status' => $announcement, 'in_reply_to_status_id' => $args['twitter_id']] );
	}

  /**
		 * Take the Twitter oAuth information provided and return an authenticated session.
		 *
		 * @access private
		 * @param string $consumer_key
		 * @param string $consumer_secret
		 * @return authenticated session
		 */
	private function auth_twitter(string $consumer_key, string $consumer_secret) {
		$oauth_token = self::OAUTH_TOKEN;
		$oauth_secret = self::OAUTH_SECRET;

		return new \Abraham\TwitterOAuth\TwitterOAuth( $consumer_key, $consumer_secret, $oauth_token, $oauth_secret );
	}

  public function theme_route_display_get_mentions(array $theme, array $params) {
  	$connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );
  	$data = $connection->get( 'statuses/mentions_timeline', ["count" => 100] );
  	$regex = "/@+([a-zA-Z0-9_]+)/";
  	$commands = array( 'help', 'mystats' );
  	$cats = array(
  		'strength', 'wisdom', 'charisma', 'defensive',
  		'constitution', 'dexterity', 'intelligence',
  		'willpower', 'perception', 'luck'
  	);
  	// Loop through the mentions returned.
  	foreach( $data as $mention ) {
  		$bits = array_filter(
  			explode( ' ' , preg_replace( $regex, '', $mention->text ))
  		);

  		$poop = array_pop($bits);

  		// Check to see if we have seen this mention before.
  		if( $this->exists( $mention->id, '{person_stats}' ) == false ) {
  			if( count($mention->entities->user_mentions) > 1 ) {

  				// This is an award or deduction.
  				$award = array_filter(
  					explode( ' ' , preg_replace( $regex, '', $mention->text ))
  				);

  				// Make sure we have all the bits we need to create an award.
  				if( count( $award ) > 2 ) {
  					$points = reset( $bits );
  					$category = array_pop( $bits );
  				} else {
  					$points = reset( $award );
  					$category = array_pop( $award );
  				}

					if( $mention->in_reply_to_screen_name != 'SocialDungeon' ) {
						$this->save_player( $mention->in_reply_to_screen_name );
						$this->save_player( $mention->user->screen_name );
					}

  				// Next we check to make sure the category in the mention is
  				// one we support.
  				if( in_array($category, $cats) ) {
  					$args = array(
  						'awarded_to'	=>	$mention->in_reply_to_screen_name,
  						'awarded_by'	=>	$mention->user->screen_name,
  						'category'		=>	$category,
  						'points'			=>	intval( $points ),
  						'awarded_on'	=>	$mention->created_at,
  						'twitter_id'	=>	$mention->id,
  					);

						// Finally we insert the award into the DB.
						if( $this->insert( $args, '{person_stats}' ) ) {
							$this->announce( $args );
						}
  				}
  			}
  		}
  	}
  }
}
?>
