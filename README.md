# DiscordNotifications

This is an extension for [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki) that sends notifications of actions in your Wiki-like editing, adding, or removing a page into [Discord](https://discord.com/) channel.

Developed by Universal Omega.

Licensed under the GPLv3 (or later) LICENSE.

## Supported MediaWiki operations to send notifications

* Article is added, removed, moved, or edited.
* Article protection settings are changed.
* Article is imported.
* New user is added.
* User is blocked.
* User groups are changed.
* File is uploaded.
* ... and each notification can be individually enabled or disabled :)

## Language Support

This extension supports language localisation. Notifications are being sent in the language set to your localSettings.php file in the variable `$wgLanguageCode`.

Want to translate this extension to your language? Just visit [translatewiki.net](https://translatewiki.net/wiki/Special:Translate/mwgithub-mediawiki-universalomega-discordnotifications) and follow the guides! :)

## Requirements

* [cURL](http://curl.haxx.se/) or ability to use PHP function `file_get_contents` for sending the data. Defaults to cURL. See the configuration parameter `$wgDiscordSendMethod` below to switch between cURL and file_get_contents.
* MediaWiki 1.43+
* Apache should have NE (NoEscape) flag on to prevent issues in URLs. By default, you should have this enabled.

## How to install

1) Create a new Discord Webhook for your channel. You can create and manage webhooks for your channel by clicking the settings icon next to the channel name in the Discord app. Read more from here: https://support.discord.com/hc/en-us/articles/228383668

2) After setting up the Webhook you will get a Webhook URL. Copy that URL as you will need it in step 4.

3) [Download the latest release of this extension](https://github.com/miraheze/DiscordNotifications/archive/main.zip), uncompress the archive, and move folder `DiscordNotifications` into your `mediawiki_installation/extensions` folder. (And instead of manually downloading the latest version, you could also just git clone this repository to that same extensions folder).

4) Add the settings listed below in your `localSettings.php`. Note that it is mandatory to set these settings for this extension to work:

```php
wfLoadExtension( 'DiscordNotifications' );

// Required. Your Discord webhook URL. Read more from here: https://support.discordapp.com/hc/en-us/articles/228383668
$wgDiscordIncomingWebhookUrl = '';

// Required. Name the message will appear to be sent from. Change this to whatever you wish it to be.
$wgDiscordFromName = $wgSitename;

// Avatar to use for messages. If blank uses the webhook's default avatar.
$wgDiscordAvatarUrl = '';

// URL into your MediaWiki installation with the trailing /.
$wgDiscordNotificationWikiUrl = 'http://your_wiki_url/';

// Wiki script name. Leave this to the default one if you do not have URL rewriting enabled.
$wgDiscordNotificationWikiUrlEnding = 'index.php?title=';

// What method will be used to send the data to the Discord server. By default, this is 'curl' which only works if you have the curl extension enabled. There have been cases where the VisualEditor extension does not work with the curl method, so in that case, the recommended solution is to use the file_get_contents method. This can be: 'curl' or 'file_get_contents'. Default: 'curl'.
$wgDiscordSendMethod = 'curl';
```

5) Enjoy the notifications in your Discord room!

## Additional options

These options can be set after including your plugin in your `localSettings.php` file.

### Customize request call method (Fix extension not working with VisualEditor)

By default, this extension uses curl to send the requests to slack's API. If you use VisualEditor and get unknown errors, do not have curl enabled on your server, or notice other problems, the recommended solution is to change the method to file_get_contents.

```php
$wgDiscordSendMethod = 'file_get_contents';
```

### Send notifications to multiple channels

You can add more webhook URLs that you want to send notifications to by adding them to this array:

```php
$wgDiscordAdditionalIncomingWebhookUrls = [
	'https://yourUrlOne.com',
	'https://yourUrlTwo...',
];
```

### Remove additional links from user and article pages

By default user and article links in the notification message will get additional links for ex. to block the user, view article history, etc. You can disable either one of those by setting the settings below to false.

```php
// If this is true, pages will get additional links in the notification message (edit | delete | history).
$wgDiscordIncludePageUrls = true;

// If this is true, users will get additional links in the notification message (block | groups | talk | contribs).
$wgDiscordIncludeUserUrls = true;

// If this is true, all minor edits made to articles will not be submitted to Discord.
$wgDiscordIgnoreMinorEdits = false;
```

### Enable new users extra information

By default, we don't show the full name, email, or IP address of the newly created user in the notification. You can enable these settings using the settings below.

```php
// If this is true, the newly created user's full name is added to the notification.
$wgDiscordShowNewUserFullName = true;

// If this is true, the newly created user email address is added to the notification.
$wgDiscordShowNewUserEmail = true;

// If this is true, the newly created user IP address is added to the notification.
$wgDiscordShowNewUserIP = true;
```

### Show edit size

By default, we show the size of the edit. You can hide this information with the setting below.

```php
$wgDiscordIncludeDiffSize = false;
```

### Disable notifications for users meeting certain conditions

By default notifications from all users will be sent to your Discord channel. If you wish to exclude users in a certain group or with certain permissions to not send notifications of any actions or specific actions, you can set the conditions with the setting below.

```php
// Sets conditions to exclude users from notifications
// Currently supports groups and permissions, and by individual actions
// To exclude bots, it would be:
$wgDiscordExcludeConditions = [
	'groups' => [
		'bot',
	]
];

// To exclude users with certain permissions, it would be:
$wgDiscordExcludeConditions = [
	'permissions' => [
		'permission1',
		'permission2',
		'etc...',
	]
];

// To exclude bots only from article edits, it would be:
$wgDiscordExcludeConditions = [
	'article_saved' => [
		'groups' => [
			'bot',
		],
	],
];

// Note: bots are always excluded from the experimental CVT feed, though this may change in the future

// To exclude sysops from article edits, but only on the experimental CVT feed, it would be:
$wgDiscordExcludeConditions = [
	'experimental' => [
		'article_saved' => [
			'groups' => [
				'sysop',
			],
		],
	],
];

/**
 * Available actions to use in exclude conditions, also indicating if they support experimental:
 *
 * article_deleted: action for when an article is deleted
 * article_inserted: action for when an article is created (supports experimental)
 * article_moved: action for when an article is moved
 * article_protected: action for when an article is protected
 * article_saved: action for when an article is edited (supports experimental)
 * file_uploaded: action for when a file is uploaded
 * import_complete: action for when an article is successfully imported
 * new_user_account: action for when a new user account is created (supports experimental)
 * user_blocked: action for when a user is blocked (supports experimental)
 * user_groups_changed: action for when the user groups of a user is changed
 */
```

### Disable notifications from certain titles or title prefixes

You can exclude notifications from certain title or title prefixes by adding them to this setting.

```php
// Actions (add, edit, modify) won't be notified to Discord channel from articles with these titles
$wgDiscordExcludeConditions = [
	'titles' => [
		'Title 1',
		'Title 2',
		'etc...',
	],
];

// Actions (add, edit, modify) won't be notified to Discord channel from articles starting with these names
$wgDiscordExcludeConditions = [
	'titles' => [
		'Exact Title',
		'prefixes' => [
			'User:',
			'Another prefix:',
			'etc...',
		],
	],
];

// Actions (add, edit, modify) won't be notified to Discord channel from articles ending with these names
$wgDiscordExcludeConditions = [
	'titles' => [
		'suffixes' => [
			'/Subpage1',
			'/Another subpage',
			'etc...',
		],
	],
];

// Actions (add, edit, modify) won't be notified to Discord channel from articles in these namespaces
$wgDiscordExcludeConditions = [
	'titles' => [
		'namespaces' => [
			NS_USER,
			NS_USER_TALK,
			'etc...',
		],
	],
];

// Actions (add, edit, modify) won't be notified to Discord channel from the main page
$wgDiscordExcludeConditions = [
	'titles' => [
		'mainpage',
	],
];

// Actions (add, edit, modify) won't be notified to Discord channel if the user is editing their own user space
$wgDiscordExcludeConditions = [
	'titles' => [
		'special_conditions' => [
			'own_user_space',
		],
	],
];

// You can also use actions and 'expermental' similar to user criteria above

/**
 * Available actions to use in title exclude conditions, also indicating if they support experimental:
 *
 * article_deleted: action for when an article is deleted
 * article_inserted: action for when an article is created (supports experimental)
 * article_saved: action for when an article is edited (supports experimental)
 */
```

### Show non-public article deletions

By default, we do not show non-public article deletion notifications. You can change this using the parameter below.

```php
$wgDiscordNotificationShowSuppressed = true;
```

### Actions to notify of

MediaWiki actions that will be sent notifications of into Discord. Set desired options to false to disable notifications of those actions.

```php
// Article added to MediaWiki
$wgDiscordNotificationEnabledActions["AddedArticle"] = true;

// Article edited in MediaWiki
$wgDiscordNotificationEnabledActions["EditedArticle"] = true;

// Article moved under a new title in MediaWiki
$wgDiscordNotificationEnabledActions["MovedArticle"] = true;

// Article protection settings changed
$wgDiscordNotificationEnabledActions["ProtectedArticle"] = true;

// Article removed from MediaWiki
$wgDiscordNotificationEnabledActions["RemovedArticle"] = true;

// Articled unremoved from MediaWiki
$wgDiscordNotificationEnabledActions["UnremovedArticle"] = true;

// Article has been imported
$wgDiscordNotificationEnabledActions["AfterImportPage"] = true;

// File uploaded
$wgDiscordNotificationEnabledActions["FileUpload"] = true;

// User or IP blocked in MediaWiki
$wgDiscordNotificationEnabledActions["BlockedUser"] = true;

// New user added to MediaWiki
$wgDiscordNotificationEnabledActions["NewUser"] = true;

// Autocreated users
$wgDiscordNotificationIncludeAutocreatedUsers = true;

// User groups changed in MediaWiki
$wgDiscordNotificationEnabledActions["UserGroupsChanged"] = true;

// Edit has been sent to the moderation queue (Extension:Moderation)
$wgDiscordNotificationEnabledActions["ModerationPending"] = true;
```

## Additional MediaWiki URL Settings

Should any of these default MediaWiki system page URLs differ in your installation, change them here.

```php
$wgDiscordNotificationWikiUrlEndingUserRights = 'Special%3AUserRights&user=';
$wgDiscordNotificationWikiUrlEndingBlockUser = 'Special:Block/';
$wgDiscordNotificationWikiUrlEndingUserPage = 'User:';
$wgDiscordNotificationWikiUrlEndingUserTalkPage = 'User_talk:';
$wgDiscordNotificationWikiUrlEndingUserContributions = 'Special:Contributions/';
$wgDiscordNotificationWikiUrlEndingBlockList = 'Special:BlockList';
$wgDiscordNotificationWikiUrlEndingEditArticle = 'action=edit';
$wgDiscordNotificationWikiUrlEndingDeleteArticle = 'action=delete';
$wgDiscordNotificationWikiUrlEndingHistory = 'action=history';
$wgDiscordNotificationWikiUrlEndingDiff = 'diff=prev&oldid=';
```

## Experimental CVT Feed

As of version 3.0.0 of DiscordNotifications, it now supports experimental CVT (countervandalism) monitoring.
You can use the below configuration options to configure it.

**Note**: These experimental configuration variables may be removed, renamed, or modified without warning.

| Configuration | Default | Description |
|---------------|---------|-------------|
| `$wgDiscordEnableExperimentalCVTFeatures`    | false | Set to true or none of the experimental CVT features will be enabled. |
| `$wgDiscordExperimentalCVTMatchLimit`        | 250   | Configure the number of characters to display before and after the found match in the experimental CVT feed. |
| `$wgDiscordExperimentalCVTMatchFilter`       | []    | An array of regexes to find matches, for sending to the experimental CVT feed. |
| `$wgDiscordExperimentalCVTSendAllIPEdits`    | true  | Sends all edits by IP users to the experimental CVT feed. |
| `$wgDiscordExperimentalCVTSendAllNewUsers`   | true  | Sends all new user account creations (not autocreations) to the experimental CVT feed. |
| `$wgDiscordExperimentalCVTSendAllUserBlocks` | true  | Sends all user blocks to the experimental CVT feed. |
| `$wgDiscordExperimentalFeedLanguageCode`     | ''    | The language code to force the experimental CVT feed localisation too. If an empty string, it will use the default content language of the wiki the notification is from. |
| `$wgDiscordExperimentalWebhook`              | ''    | The Discord incoming webhook URL to use for the experimental CVT feed. If an empty string, the experimental CVT feed features will be disabled. |
| `$wgDiscordExperimentalNewUsersWebhook`      | ''    | The Discord incoming webhook URL to use for an experimental new users feed (not including autocreations). If this is set, they will be sent here rather than the experimental CVT feed. |
| `$wgDiscordExperimentalUserBlocksWebhook`    | ''    | The Discord incoming webhook URL to use for an experimental user blocks feed. If this is set, they will be sent here rather than the experimental CVT feed. |
| `$wgDiscordNotificationCentralAuthWikiUrl`   | ''    | The URL for the wiki to use in the CentralAuth URL. The CentralAuth URL will only appear in the experimental CVT feed. If set to an empty string, no CentralAuth link will appear. |


## Security Vulnerabilities

If you believe you have found a security vulnerability in any part of our code, please do not post it publicly by using our wikis or bug trackers for that; rather, please read our [security page](https://meta.miraheze.org/wiki/Special:MyLanguage/Security) carefully, and follow the instructions.

As a quick overview, you can email security concerns to security@miraheze.org or if you'd like, you can instead directly create a security-related task [here](https://issue-tracker.miraheze.org/maniphest/task/edit/form/2/), but please leave the "Security" project on the issue.
