Welcome back to the madhouse friends! Today we're going to be building on [Chapter 1 of this series](https://chrisjdavis.org/social-dungeon-chapter-1), so make sure you have gone through it and are up to speed.

The repo for this chapter can be [found here](https://github.com/chrisjdavis/socialdungeon-chapter-2). Happy programming.

So if you remember in the previous chapter, we focused on creating the Twitter bot that will be the backbone of this whole shebang. In this chapter, we'll be looking at how to use the info that our bot is getting for each account and having our bot respond when it has recorded an award. Pretty straightforward. Let's go!

### Social Dunegon Saves
So the first thing we need to do is make sure we are saving the account info for the accounts that are being awarded points, as well as for the accounts that are doing the awarding. For that, we need a new method, conveniently named *save_player*!

As before we'll take build this out bit by bit. So let's start with the method signature.

<pre>
<code>
private function save_player(string $handle) {}
</code>
</pre>

Just a couple of things to go over here. The method is marked *private* since it is exclusively used by this class. This is good for various reasons, from security to code structure sanity.

You can also see that we have begun adding the data type (in this case *string*) when telling our method what to expect as input. This is another solid practice from a security standpoint, as well as just good practice to get into.

Now when we call this method if we pass it something other than a string, it will throw an error. Good stuff. I have updated the methods from our previous tutorial to include data types as well. Moving on!

<pre>
<code>
private function save_player(string $handle) {
  $boss = false;
  $connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );
  $details = $connection->get( 'users/show', ["screen_name" => $handle] );
}
</code>
</pre>

Okay, this should look familiar if you followed along with Chapter 1. We create a connection with Twitter and then get the details about the twitter account name, here called *$handle*.

The only new bit is the variable *$boss*. This will come into play later. Okay, now that we have our account info, we need to do something with it. First up, let's calculate the attack and defense of this player, and assign some attributes

<pre>
<code>
private function save_player(string $handle) {
  $boss = '0';
  $connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );
  $details = $connection->get( 'users/show', ["screen_name" => $handle] );

	$class = DB::get_row(
		'select id from {character_classes} order by rand() limit 1'
	);

	$alignment = DB::get_row(
		'select id from {character_alignments} order by rand() limit 1'
	);

	$weapon = DB::get_row(
		'select id from {weapons} where class_id = :cid', array('cid' => $class->id)
	);

	$attack = $details->followers_count;
	$defense = $details->friends_count;
}
</code>
</pre>

There are a lot of ways to come up with attack and defense points. This is the one I chose. As for the attributes, you may have noticed we're referencing tables that don't exist yet, so let's fix that!

<pre>
<code>
private function setup_character_class_table() {
  $sql = "CREATE TABLE {\$prefix}character_classes (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    slug varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
    description text COLLATE utf8mb4_general_ci,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
  DB::dbdelta( $sql );
}
</code>
</pre>

<pre>
<code>
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
</code>
</pre>
<pre>
<code>
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
</code>
</pre>

These are the schemas that add to your file, or you can run them manually against your SQL database. You will, of course, need to populate these tables with data, which you can choose for yourself, or just grab the data that I am using, which you can find in the repo for this chapter.

Now that we have those in place, let's keep on trucking.

### A class, alignment, and weapon walk into a bar

Now that we can get a randomly, or not so randomly, set attributes for our player, it's time to save that info somewhere. Oh, but first let's take a look at the *boss* variable.

<pre>
<code>
private function save_player(string $handle) {
  $boss = false;
  $connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );
  $details = $connection->get( 'users/show', ["screen_name" => $handle] );

	$class = DB::get_row(
		'select id from {character_classes} order by rand() limit 1'
	);

	$alignment = DB::get_row(
		'select id from {character_alignments} order by rand() limit 1'
	);

	$weapon = DB::get_row(
		'select id from {weapons} where class_id = :cid', array('cid' => $class->id)
	);

	$attack = $details->followers_count;
	$defense = $details->friends_count;

	if( $details->verified == true ) {
		$boss = true;
	}
}
</code>
</pre>

So this is pretty straightforward, and hopefully pretty interesting. Since larger, more affluent accounts on Twitter become verified I thought it would be a little unfair to have them be able to play with the rest of us peons, given they usually have a massive follower and/or following counts.

But I still wanted to do something with them, since odds are at some point another player would award them points. I [do it](https://socialdungeon.com/sheet/donttrythis) all the time. I mean, [a lot](https://socialdungeon.com/sheet/elonmusk).

So I had the idea to make these accounts bosses in the game we're building. Every good campaign needs a villain to overthrow at the end, amiright? Okay, now we have determined if the player in question is a boss or not, let's finally save that data!

<pre>
<code>
private function save_player(string $handle) {
  $boss = false;
  $connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );
  $details = $connection->get( 'users/show', ["screen_name" => $handle] );

	$class = DB::get_row(
		'select id from {character_classes} order by rand() limit 1'
	);

	$alignment = DB::get_row(
		'select id from {character_alignments} order by rand() limit 1'
	);

	$weapon = DB::get_row(
		'select id from {weapons} where class_id = :cid', array('cid' => $class->id)
	);

	$attack = $details->followers_count;
	$defense = $details->friends_count;

	if( $details->verified == true ) {
		$boss = true;
	}

	$args = array(
		'twitter_id'        =>  $details->id,
		'name'                  =>  $details->name,
		'handle'                =>  $details->screen_name,
		'description'       =>  $details->description,
		'avatar'                =>  $details->profile_image_url_https,
		'class_id'          =>  $class->id,
		'alignment_id'  =>  $alignment->id,
		'weapon_id'         =>  $weapon->id,
		'kingdom_id'        =>  $kingdom,
		'defense_points'    =>  $defense,
		'attack_points'     =>  $attack,
		'boss'                      =>  $boss,
	);

	if( $this->exists( $details->id, '{account_details}' ) == false ) {
		if( !empty($args) ) {
			$this->insert( $args, '{account_details}' );
		}
	} else {
		$pargs = array('avatr' => $details->profile_image_url_https, 'id' => $details->id);
		DB::query(
			'update {account_details} set avatar = :avatr where twitter_id = :id', $pargs
		);
	}
}
</code>
</pre>

As you can see we build out an array that contains all the info we need to create a record for a player, but before we can save this data we need to make sure the player doesn't already exist, which we do by calling *exists()* and passing it the ID of the tweet.

If this returns false we can confidently call *insert()* and save this player. Whoo! Okay, now that we can save the details of a player, we need to call this method from the mentions method we set up in the previous chapter. We want to add it just above where we are checking that the award matches categories we support, like so.

<pre>
<code>
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

	foreach( $data as $mention ) {
		$bits = array_filter(
			explode( ' ' , preg_replace( $regex, '', $mention->text ))
		);

		$poop = array_pop($bits);

		if( $this->exists( $mention->id, '{person_stats}' ) == false ) {
			if( count($mention->entities->user_mentions) > 1 ) {
				$award = array_filter(
					explode( ' ' , preg_replace( $regex, '', $mention->text ))
				);

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

				if( in_array($category, $cats) ) {
					$args = array(
						'awarded_to'	=>	$mention->in_reply_to_screen_name,
						'awarded_by'	=>	$mention->user->screen_name,
						'category'		=>	$category,
						'points'			=>	intval( $points ),
						'awarded_on'	=>	$mention->created_at,
						'twitter_id'	=>	$mention->id,
					);

					$this->insert( $args, '{person_stats}' );
				}
			}
		}
	}
}
</code>
</pre>

So just a quick note about how we are referencing *save_player()* here. You will of course first notice that we have wrapped the two calls to this method in a check for the social dungeon account.

The SocialDungeon account exists in the game, but merely as the "all powerful" DM, so allowing for awards and all that to be applied to it wouldn't really be a good idea. The important bit though is that we are calling *save_player()* twice here, once for the account being awarded, and the second time for the account doing the awarding.

This is important for later when we are displaying all of this data via a lovingly crafted front end. We want to be able to show, and link to, the account that is awarding points.

Wow okay. So we have one last thing to do and we can call this chapter done. It's time to give Social Dungeon a voice, and we do that with a new method called *announce()*.

<pre>
<code>
private function announce(array $args) {
	$announcement = '@' . $args['awarded_by'] . ' awarded '
						. $args['points'] . ' ' . $args['category']
						. ' points to @' . $args['awarded_to'];

	$connection = $this->auth_twitter( self::TWITKEY, self::TWITSECRET );

	$statues = $connection->post(
		'statuses/update',
		['status' => $announcement,
		'in_reply_to_status_id' => $args['twitter_id']]
	);
}
</code>
</pre>

Let's break it down, one more time. First we need to craft our tweet. I opted for a standard tweet body but you can make yours more custom for each tweet, up to you. Once we have the tweet body composed, it's time to send it!

Again this is pretty straightforward. First we create a connection to Twitter with our *auth_twitter()* method, and then call *post()* which sends a tweet on behalf the authenticated account, which in our case is SocialDungeon.

The *post()* method takes a few arguments, for our purposes we are going to pass it the tweet_body we have composed and the ID of the tweet that we are responding to, which in our case is the tweet that awarded points to an account.

And that's it. If you've written all the code properly a reply will be created to the original tweet announcing that the award has been given. Now we just need to call this from our mention method.

<pre>
<code>
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

	foreach( $data as $mention ) {
		$bits = array_filter(
			explode( ' ' , preg_replace( $regex, '', $mention->text ))
		);

		$poop = array_pop($bits);

		if( $this->exists( $mention->id, '{person_stats}' ) == false ) {
			if( count($mention->entities->user_mentions) > 1 ) {
				$award = array_filter(
					explode( ' ' , preg_replace( $regex, '', $mention->text ))
				);

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

				if( in_array($category, $cats) ) {
					$args = array(
						'awarded_to'	=>	$mention->in_reply_to_screen_name,
						'awarded_by'	=>	$mention->user->screen_name,
						'category'		=>	$category,
						'points'			=>	intval( $points ),
						'awarded_on'	=>	$mention->created_at,
						'twitter_id'	=>	$mention->id,
					);

					if( $this->insert( $args, '{person_stats}' ) ) {
						$this->announce( $args );
					}
				}
			}
		}
	}
}
</code>
</pre>

To make this all work, we wrap our *insert()* in an if statement and and call *announce()* from within it, meaning it's only called if our insert returns true.

And that is it people. At this point you have a plugin that can authenticate to Twitter, grab all the mentions that it finds, act on them and save the data for the Twitter accounts involved.

Pat yourself on the back, and get ready for the next chapter, where we start doing something with this tasty data.
