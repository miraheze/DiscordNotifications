<?php

declare( strict_types = 1 );

namespace Miraheze\DiscordNotifications;

use Flow\Collection\PostCollection;
use Flow\Model\UUID;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use WikiPage;

class DiscordNotifier {

	public const CONSTRUCTOR_OPTIONS = [
		'DiscordAdditionalIncomingWebhookUrls',
		'DiscordAvatarUrl',
		'DiscordCurlProxy',
		'DiscordDisableEmbedFooter',
		'DiscordExcludeConditions',
		'DiscordExperimentalCVTUsernameFilter',
		'DiscordFromName',
		'DiscordIncludePageUrls',
		'DiscordIncludeUserUrls',
		'DiscordIncomingWebhookUrl',
		'DiscordNotificationCentralAuthWikiUrl',
		'DiscordNotificationWikiUrl',
		'DiscordNotificationWikiUrlEnding',
		'DiscordNotificationWikiUrlEndingBlockUser',
		'DiscordNotificationWikiUrlEndingDeleteArticle',
		'DiscordNotificationWikiUrlEndingDiff',
		'DiscordNotificationWikiUrlEndingEditArticle',
		'DiscordNotificationWikiUrlEndingHistory',
		'DiscordNotificationWikiUrlEndingUserContributions',
		'DiscordNotificationWikiUrlEndingUserPage',
		'DiscordNotificationWikiUrlEndingUserRights',
		'DiscordNotificationWikiUrlEndingUserTalkPage',
		'DiscordSendMethod',
		'Sitename',
	];

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var ServiceOptions */
	private $options;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserGroupManager */
	private $userGroupManager;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param ServiceOptions $options
	 * @param PermissionManager $permissionManager
	 * @param UserGroupManager $userGroupManager
	 */
	public function __construct(
		MessageLocalizer $messageLocalizer,
		ServiceOptions $options,
		PermissionManager $permissionManager,
		UserGroupManager $userGroupManager
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->messageLocalizer = $messageLocalizer;
		$this->options = $options;
		$this->permissionManager = $permissionManager;
		$this->userGroupManager = $userGroupManager;
	}

	/**
	 * Sends the message into Discord.
	 *
	 * @param string $message
	 * @param ?UserIdentity $user
	 * @param string $action
	 * @param array $embedFields
	 * @param ?string $webhook
	 */
	public function notify( string $message, ?UserIdentity $user, string $action, array $embedFields = [], ?string $webhook = null, ?Title $title = null ) {
		if ( $user && $this->userIsExcluded( $user, $action, (bool)$webhook ) ) {
			// Don't send notifications if user meets exclude conditions
			return;
		}

		if ( $user && $title && $this->titleIsExcluded( $title, $user, $action, (bool)$webhook ) ) {
			return;
		}

		$discordFromName = $this->options->get( 'DiscordFromName' );
		if ( $discordFromName == '' ) {
			$discordFromName = $this->options->get( 'Sitename' );
		}

		$message = preg_replace( '~(<)(http)([^|]*)(\|)([^\>]*)(>)~', '[$5]($2$3)', $message );
		$message = str_replace( [ "\r", "\n" ], '', $message );

		switch ( $action ) {
			case 'article_saved':
			case 'flow':
			case 'import_complete':
			case 'user_groups_changed':
				$color = '2993970';
				break;
			case 'article_inserted':
			case 'file_uploaded':
			case 'new_user_account':
				$color = '3580392';
				break;
			case 'article_deleted':
			case 'user_blocked':
				$color = '15217973';
				break;
			case 'article_moved':
				$color = '14038504';
				break;
			case 'article_protected':
				$color = '3493864';
				break;
			default:
				$color = '11777212';
		}

		$embed = ( new DiscordEmbedBuilder() )
			->setColor( $color )
			->setDescription( $message )
			->setUsername( $discordFromName );

		if ( $this->options->get( 'DiscordAvatarUrl' ) ) {
			$embed->setAvatarUrl( $this->options->get( 'DiscordAvatarUrl' ) );
		}

		foreach ( $embedFields as $name => $value ) {
			if ( !$value ) {
				// Don't add empty fields
				continue;
			}

			$embed->addField( $name, $value );
		}

		// Temporary
		if ( !$this->options->get( 'DiscordDisableEmbedFooter' ) || $webhook ) {
			$embed->setFooter( 'DiscordNotifications v3 — Let @cosmicalpha know of any issues.' );
		}

		$post = $embed->build();

		// Use file_get_contents to send the data. Note that you will need to have allow_url_fopen enabled in php.ini for this to work.
		if ( $this->options->get( 'DiscordSendMethod' ) == 'file_get_contents' ) {
			$this->sendHttpRequest( $webhook ?? $this->options->get( 'DiscordIncomingWebhookUrl' ), $post );

			if ( !$webhook && $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' ) && is_array( $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' ) ) ) {
				for ( $i = 0; $i < count( $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' ) ); ++$i ) {
					$this->sendHttpRequest( $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' )[$i], $post );
				}
			}
		} else {
			// Call the Discord API through cURL (default way). Note that you will need to have cURL enabled for this to work.
			$this->sendCurlRequest( $webhook ?? $this->options->get( 'DiscordIncomingWebhookUrl' ), $post );

			if ( !$webhook && $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' ) && is_array( $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' ) ) ) {
				for ( $i = 0; $i < count( $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' ) ); ++$i ) {
					$this->sendCurlRequest( $this->options->get( 'DiscordAdditionalIncomingWebhookUrls' )[$i], $post );
				}
			}
		}
	}

	/**
	 * @param string $url
	 * @param string $postData
	 */
	private function sendCurlRequest( string $url, string $postData ) {
		$h = curl_init();
		curl_setopt( $h, CURLOPT_URL, $url );

		if ( $this->options->get( 'DiscordCurlProxy' ) ) {
			curl_setopt( $h, CURLOPT_PROXY, $this->options->get( 'DiscordCurlProxy' ) );
		}

		curl_setopt( $h, CURLOPT_POST, 1 );
		curl_setopt( $h, CURLOPT_POSTFIELDS, $postData );
		curl_setopt( $h, CURLOPT_RETURNTRANSFER, true );

		// Set 10 second timeout to connection
		curl_setopt( $h, CURLOPT_CONNECTTIMEOUT, 10 );

		// Set global 10 second timeout to handle all data
		curl_setopt( $h, CURLOPT_TIMEOUT, 10 );

		// Set Content-Type to application/json
		curl_setopt( $h, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: ' . strlen( $postData )
		] );

		// Execute the curl script
		$curl_output = curl_exec( $h );

		curl_close( $h );
	}

	/**
	 * @param string $url
	 * @param string $postData
	 */
	private function sendHttpRequest( string $url, string $postData ) {
		$extraData = [
			'http' => [
				'header'  => 'Content-type: application/json',
				'method'  => 'POST',
				'content' => $postData,
			],
		];

		$context = stream_context_create( $extraData );
		$result = file_get_contents( $url, false, $context );
	}

	/**
	 * Replaces some special characters on urls. This has to be done as Discord webhook api does not accept urlencoded text.
	 *
	 * @param string $url
	 * @return string
	 */
	public function parseurl( string $url ): string {
		$url = str_replace( ' ', '_', $url );
		$url = str_replace( '(', '%28', $url );
		$url = str_replace( ')', '%29', $url );
		$url = str_replace( '?', '%3F', $url );

		return $url;
	}

	/**
	 * Gets nice HTML text for user containing the link to user page
	 * and also links to user site, groups editing, talk and contribs pages.
	 *
	 * @param UserIdentity $user UserIdentity or if CentralAuth is installed, CentralAuthGroupMembershipProxy
	 * @param string $languageCode
	 * @param bool $includeCentralAuthUrl
	 * @return string
	 */
	public function getDiscordUserText( $user, string $languageCode = '', bool $includeCentralAuthUrl = false ): string {
		$userName = $user->getName();
		$user_url = str_replace( '&', '%26', $userName );

		$userName = str_replace( '>', '\>', $userName );

		if ( $this->options->get( 'DiscordIncludeUserUrls' ) ) {
			$userUrls = sprintf(
				'%s (%s | %s | %s | %s',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $this->options->get( 'DiscordNotificationWikiUrlEndingUserPage' ) . $user_url ) . '|' . $userName . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $this->options->get( 'DiscordNotificationWikiUrlEndingBlockUser' ) . $user_url ) . '|' . $this->getMessageInLanguage( 'discordnotifications-block', $languageCode ) . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $this->options->get( 'DiscordNotificationWikiUrlEndingUserRights' ) . $user_url ) . '|' . $this->getMessageInLanguage( 'discordnotifications-groups', $languageCode ) . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $this->options->get( 'DiscordNotificationWikiUrlEndingUserTalkPage' ) . $user_url ) . '|' . $this->getMessageInLanguage( 'discordnotifications-talk', $languageCode ) . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $this->options->get( 'DiscordNotificationWikiUrlEndingUserContributions' ) . $user_url ) . '|' . $this->getMessageInLanguage( 'discordnotifications-contribs', $languageCode ) . '>'
			);

			if (
				$includeCentralAuthUrl &&
				ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) &&
				$this->options->get( 'DiscordNotificationCentralAuthWikiUrl' ) &&
				$user->isRegistered()
			) {
				$userUrls .= ' | <' . $this->parseurl( $this->options->get( 'DiscordNotificationCentralAuthWikiUrl' ) . 'wiki/Special:CentralAuth/' . $user_url ) . '|' . $this->getMessageInLanguage( 'discordnotifications-centralauth', $languageCode ) . '>';
			}

			$userUrls .= ')';

			return $userUrls;
		} else {
			return '<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $this->options->get( 'DiscordNotificationWikiUrlEndingUserPage' ) . $user_url ) . '|' . $userName . '>';
		}
	}

	/**
	 * Gets nice HTML text for article containing the link to article page
	 * and also into edit, delete and article history pages.
	 *
	 * @param WikiPage $wikiPage
	 * @param bool $diff
	 * @param string $languageCode
	 * @return string
	 */
	public function getDiscordArticleText( WikiPage $wikiPage, bool $diff = false, string $languageCode = '' ): string {
		$title = $wikiPage->getTitle()->getFullText();
		$title_url = str_replace( '&', '%26', $title );
		$prefix = '<' . $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $title_url;

		if ( $this->options->get( 'DiscordIncludePageUrls' ) ) {
			$out = sprintf(
				'%s (%s | %s | %s',
				$this->parseurl( $prefix ) . '|' . $title . '>',
				$this->parseurl( $prefix . $this->options->get( 'DiscordNotificationWikiUrlEndingEditArticle' ) ) . '|' . $this->getMessageInLanguage( 'discordnotifications-edit', $languageCode ) . '>',
				$this->parseurl( $prefix . $this->options->get( 'DiscordNotificationWikiUrlEndingDeleteArticle' ) ) . '|' . $this->getMessageInLanguage( 'discordnotifications-delete', $languageCode ) . '>',
				$this->parseurl( $prefix . $this->options->get( 'DiscordNotificationWikiUrlEndingHistory' ) ) . '|' . $this->getMessageInLanguage( 'discordnotifications-history', $languageCode ) . '>'
			);

			if ( $diff ) {
				$revisionId = $wikiPage->getRevisionRecord()->getId();

				$out .= ' | ' . $this->parseurl( $prefix . $this->options->get( 'DiscordNotificationWikiUrlEndingDiff' ) . $revisionId ) . '|' . $this->getMessageInLanguage( 'discordnotifications-diff', $languageCode ) . '>)';
			} else {
				$out .= ')';
			}

			return $out . "\n";
		} else {
			return $this->parseurl( $prefix ) . '|' . $title . '>';
		}
	}

	/**
	 * Gets nice HTML text for title object containing the link to article page
	 * and also into edit, delete and article history pages.
	 *
	 * @param Title $title
	 * @return string
	 */
	public function getDiscordTitleText( Title $title ): string {
		$titleName = $title->getFullText();
		$title_url = str_replace( '&', '%26', $titleName );

		if ( $this->options->get( 'DiscordIncludePageUrls' ) ) {
			return sprintf(
				'%s (%s | %s | %s)',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $title_url ) . '|' . $titleName . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $title_url . $this->options->get( 'DiscordNotificationWikiUrlEndingEditArticle' ) ) . '|' . $this->getMessage( 'discordnotifications-edit' ) . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $title_url . $this->options->get( 'DiscordNotificationWikiUrlEndingDeleteArticle' ) ) . '|' . $this->getMessage( 'discordnotifications-delete' ) . '>',
				'<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $title_url . $this->options->get( 'DiscordNotificationWikiUrlEndingHistory' ) ) . '|' . $this->getMessage( 'discordnotifications-history' ) . '>'
			);
		} else {
			return '<' . $this->parseurl( $this->options->get( 'DiscordNotificationWikiUrl' ) . $this->options->get( 'DiscordNotificationWikiUrlEnding' ) . $title_url ) . '|' . $titleName . '>';
		}
	}

	/**
	 * Returns whether the given title should be excluded
	 *
	 * @param Title $title
	 * @param UserIdentity $user
	 * @param string $action
	 * @param bool $experimental
	 * @return bool
	 */
	public function titleIsExcluded( Title $title, UserIdentity $user, string $action, bool $experimental ): bool {
		$excludeConditions = $this->options->get( 'DiscordExcludeConditions' );

		if ( !$excludeConditions ) {
			// Exit early if no conditions are set
			return false;
		}

		$titleName = $title->getText();

		if ( is_array( $excludeConditions['titles'] ?? null ) ) {
			if ( in_array( $titleName, $excludeConditions['titles'] ) ) {
				return true;
			}

			if ( in_array( 'mainpage', $excludeConditions['titles'] ) && $title->isMainPage() ) {
				return true;
			}

			if ( is_array( $excludeConditions['titles']['special_conditions'] ?? null ) ) {
				if (
					in_array( 'own_user_space', $excludeConditions['titles']['special_conditions'] ) &&
					$title->inNamespaces( NS_USER, NS_USER_TALK ) &&
					$user->getName() === $title->getRootText()
				) {
					return true;
				}
			}

			if ( is_array( $excludeConditions['titles']['namespaces'] ?? null ) ) {
				if ( $title->inNamespaces( $excludeConditions['titles']['namespaces'] ) ) {
					return true;
				}
			}

			if ( is_array( $excludeConditions['titles']['prefixes'] ?? null ) ) {
				foreach ( $excludeConditions['titles']['prefixes'] as $currentExclude ) {
					if ( strpos( $titleName, $currentExclude ) === 0 || $title->getNsText() === $currentExclude ) {
						return true;
					}
				}
			}

			if ( is_array( $excludeConditions['titles']['suffixes'] ?? null ) ) {
				foreach ( $excludeConditions['titles']['suffixes'] as $currentExclude ) {
					if ( str_ends_with( $titleName, $currentExclude ) ) {
						return true;
					}
				}
			}
		}

		if ( $experimental ) {
			if ( is_array( $excludeConditions['experimental'] ?? null ) ) {
				$experimentalConditions = $excludeConditions['experimental'];

				if ( is_array( $experimentalConditions['titles'] ?? null ) ) {
					if ( in_array( $titleName, $experimentalConditions['titles'] ) ) {
						return true;
					}

					if ( in_array( 'mainpage', $experimentalConditions['titles'] ) && $title->isMainPage() ) {
						return true;
					}

					if ( is_array( $experimentalConditions['titles']['special_conditions'] ?? null ) ) {
						if (
							in_array( 'own_user_space', $experimentalConditions['titles']['special_conditions'] ) &&
							$title->inNamespaces( NS_USER, NS_USER_TALK ) &&
							$user->getName() === $title->getRootText()
						) {
							return true;
						}
					}

					if ( is_array( $experimentalConditions['titles']['namespaces'] ?? null ) ) {
						if ( $title->inNamespaces( $experimentalConditions['titles']['namespaces'] ) ) {
							return true;
						}
					}

					if ( is_array( $experimentalConditions['titles']['prefixes'] ?? null ) ) {
						foreach ( $experimentalConditions['titles']['prefixes'] as $currentExclude ) {
							if ( strpos( $titleName, $currentExclude ) === 0 || $title->getNsText() === $currentExclude ) {
								return true;
							}
						}
					}

					if ( is_array( $experimentalConditions['titles']['suffixes'] ?? null ) ) {
						foreach ( $experimentalConditions['titles']['suffixes'] as $currentExclude ) {
							if ( str_ends_with( $titleName, $currentExclude ) ) {
								return true;
							}
						}
					}
				}

				if ( is_array( $experimentalConditions[$action] ?? null ) ) {
					$actionConditions = $experimentalConditions[$action];

					if ( !empty( $actionConditions['titles'] ) && is_array( $actionConditions['titles'] ) ) {
						if ( in_array( $titleName, $actionConditions['titles'] ) ) {
							return true;
						}

						if ( in_array( 'mainpage', $actionConditions['titles'] ) && $title->isMainPage() ) {
							return true;
						}

						if ( is_array( $actionConditions['titles']['special_conditions'] ?? null ) ) {
							if (
								in_array( 'own_user_space', $actionConditions['titles']['special_conditions'] ) &&
								$title->inNamespaces( NS_USER, NS_USER_TALK ) &&
								$user->getName() === $title->getRootText()
							) {
								return true;
							}
						}

						if ( is_array( $actionConditions['titles']['namespaces'] ?? null ) ) {
							if ( $title->inNamespaces( $actionConditions['titles']['namespaces'] ) ) {
								return true;
							}
						}

						if ( is_array( $actionConditions['titles']['prefixes'] ?? false ) ) {
							foreach ( $actionConditions['titles']['prefixes'] as $currentExclude ) {
								if ( strpos( $titleName, $currentExclude ) === 0 || $title->getNsText() === $currentExclude ) {
									return true;
								}
							}
						}

						if ( is_array( $actionConditions['titles']['suffixes'] ?? null ) ) {
							foreach ( $actionConditions['titles']['suffixes'] as $currentExclude ) {
								if ( str_ends_with( $titleName, $currentExclude ) ) {
									return true;
								}
							}
						}
					}
				}
			}
		} elseif ( is_array( $excludeConditions[$action] ?? null ) ) {
			$actionConditions = $excludeConditions[$action];

			if ( is_array( $actionConditions['titles'] ?? null ) ) {
				if ( in_array( $titleName, $actionConditions['titles'] ) ) {
					return true;
				}

				if ( in_array( 'mainpage', $actionConditions['titles'] ) && $title->isMainPage() ) {
					return true;
				}

				if ( is_array( $actionConditions['titles']['special_conditions'] ?? null ) ) {
					if (
						in_array( 'own_user_space', $actionConditions['titles']['special_conditions'] ) &&
						$title->inNamespaces( NS_USER, NS_USER_TALK ) &&
						$user->getName() === $title->getRootText()
					) {
						return true;
					}
				}

				if ( is_array( $actionConditions['titles']['namespaces'] ?? null ) ) {
					if ( $title->inNamespaces( $actionConditions['titles']['namespaces'] ) ) {
						return true;
					}
				}

				if ( is_array( $actionConditions['titles']['prefixes'] ?? null ) ) {
					foreach ( $actionConditions['titles']['prefixes'] as $currentExclude ) {
						if ( strpos( $titleName, $currentExclude ) === 0 || $title->getNsText() === $currentExclude ) {
							return true;
						}
					}
				}

				if ( is_array( $actionConditions['titles']['suffixes'] ?? null ) ) {
					foreach ( $actionConditions['titles']['suffixes'] as $currentExclude ) {
						if ( str_ends_with( $titleName, $currentExclude ) ) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Returns whether the users exclude conditions are met
	 *
	 * @param UserIdentity $user
	 * @param string $action
	 * @param bool $experimental
	 * @return bool
	 */
	public function userIsExcluded( UserIdentity $user, string $action, bool $experimental ): bool {
		$excludeConditions = $this->options->get( 'DiscordExcludeConditions' );

		if ( !$excludeConditions ) {
			// Exit early if no conditions are set
			return false;
		}

		if ( is_array( $excludeConditions['permissions'] ?? null ) ) {
			if ( array_intersect( $excludeConditions['permissions'], $this->permissionManager->getUserPermissions( $user ) ) ) {
				// Users with the permissions suppress notifications for any action, including expermental feeds
				return true;
			}
		}

		if ( is_array( $excludeConditions['groups'] ?? null ) ) {
			if ( array_intersect( $excludeConditions['groups'], $this->userGroupManager->getUserEffectiveGroups( $user ) ) ) {
				// Users with the group suppress notifications for any action, including expermental feeds
				return true;
			}
		}

		if ( is_array( $excludeConditions['users'] ?? null ) ) {
			if ( in_array( $user->getName(), $excludeConditions['users'] ) ) {
				// Individual users suppress notifications for any action, including expermental feeds
				return true;
			}
		}

		if ( $experimental ) {
			if ( is_array( $excludeConditions['experimental'] ?? null ) ) {
				if ( is_array( $excludeConditions['experimental']['permissions'] ?? null ) && array_intersect( $excludeConditions['experimental']['permissions'], $this->permissionManager->getUserPermissions( $user ) ) ) {
					// Users with the permissions suppress notifications for the experimental condition
					return true;
				}

				if ( is_array( $excludeConditions['experimental']['groups'] ?? null ) && array_intersect( $excludeConditions['experimental']['groups'], $this->userGroupManager->getUserEffectiveGroups( $user ) ) ) {
					// Users with the groups suppress notifications for the experimental condition
					return true;
				}

				if ( is_array( $excludeConditions['experimental']['users'] ?? null ) && in_array( $user->getName(), $excludeConditions['experimental']['users'] ) ) {
					// Individual users suppress notifications for the experimental condition
					return true;
				}

				if ( is_array( $excludeConditions['experimental'][$action] ?? null ) ) {
					$actionConditions = $excludeConditions['experimental'][$action];

					if ( is_array( $actionConditions['permissions'] ?? null ) && array_intersect( $actionConditions['permissions'], $this->permissionManager->getUserPermissions( $user ) ) ) {
						// Users with the permissions suppress notifications if matching action
						return true;
					}

					if ( is_array( $actionConditions['groups'] ?? null ) && array_intersect( $actionConditions['groups'], $this->userGroupManager->getUserEffectiveGroups( $user ) ) ) {
						// Users with the groups suppress notifications if matching action
						return true;
					}

					if ( is_array( $actionConditions['users'] ?? null ) && in_array( $user->getName(), $actionConditions['users'] ) ) {
						// Individual users suppress notifications if matching action
						return true;
					}
				}
			}
		} elseif ( is_array( $excludeConditions[$action] ?? null ) ) {
			$actionConditions = $excludeConditions[$action];

			if ( is_array( $actionConditions['permissions'] ?? null ) && array_intersect( $actionConditions['permissions'], $this->permissionManager->getUserPermissions( $user ) ) ) {
				// Users with the permissions suppress notifications if matching action
				return true;
			}

			if ( is_array( $actionConditions['groups'] ?? null ) && array_intersect( $actionConditions['groups'], $this->userGroupManager->getUserEffectiveGroups( $user ) ) ) {
				// Users with the groups suppress notifications if matching action
				return true;
			}

			if ( is_array( $actionConditions['users'] ?? null ) && in_array( $user->getName(), $actionConditions['users'] ) ) {
				// Individual users suppress notifications if matching action
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns whether the username matches filters
	 *
	 * @param string $username
	 * @return bool
	 */
	public function isOffensiveUsername( string $username ): bool {
		$usernameFilter = $this->options->get( 'DiscordExperimentalCVTUsernameFilter' );

		$keywords = $usernameFilter['keywords'] ?? [];
		$patterns = $usernameFilter['patterns'] ?? [];

		// Check if username contains a match in the keywords filter
		foreach ( $keywords as $keyword ) {
			if ( stripos( $username, $keyword ) !== false ) {
				return true;
			}
		}

		// Check if username matches any of the patterns filter
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $username ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $key
	 * @param string ...$params
	 * @return string
	 */
	public function getMessage( string $key, string ...$params ): string {
		if ( $params ) {
			return $this->messageLocalizer->msg( $key, ...$params )->inContentLanguage()->text();
		} else {
			return $this->messageLocalizer->msg( $key )->inContentLanguage()->text();
		}
	}

	/**
	 * @param string $key
	 * @param string ...$params
	 * @return string
	 */
	public function getMessageWithPlaintextParams( string $key, string ...$params ): string {
		return $this->messageLocalizer->msg( $key )->plaintextParams( ...$params )->inContentLanguage()->text();
	}

	/**
	 * @param string $key
	 * @param string $languageCode
	 * @param string ...$params
	 * @return string
	 */
	public function getMessageInLanguage( string $key, string $languageCode, string ...$params ): string {
		if ( !$languageCode ) {
			return $this->getMessage( $key, ...$params );
		}

		if ( $params ) {
			return $this->messageLocalizer->msg( $key, ...$params )->inLanguage( $languageCode )->text();
		} else {
			return $this->messageLocalizer->msg( $key )->inLanguage( $languageCode )->text();
		}
	}

	/**
	 * Convert the HTML diff to a human-readable format so it can be in the Discord embed
	 *
	 * @param string $diff
	 * @return string
	 */
	public function getPlainDiff( string $diff ): string {
		$replacements = [
			html_entity_decode( '&nbsp;' ) => ' ',
			html_entity_decode( '&minus;' ) => '-',
			'+' => "\n+",
		];

		// Preserve markers when stripping tags
		$diff = str_replace( '<td class="diff-marker"></td>', ' ', $diff );
		$diff = preg_replace( '@<td colspan="2"( class="(?:diff-side-deleted|diff-side-added)")?></td>@', "\n\n", $diff );
		$diff = preg_replace( '/data-marker="([^"]*)">/', '>$1', $diff );

		return str_replace( array_keys( $replacements ), array_values( $replacements ),
			strip_tags( $diff ) );
	}

	/**
	 * Make links in summary human-readable in Discord embeds
	 *
	 * @param string $summary
	 * @return string
	 */
	public function parseSummary( string $summary ): string {
		return preg_replace_callback(
			'/\[\[(.+?)(?:\|(.+?))?]]/i',
			static function ( $matches ) {
				$linkLabel = $matches[2] ?? $matches[1];
				$linkUrl = preg_replace( '/\s+/', '_', $matches[1] );

				return "[$linkLabel]($linkUrl)";
			},
			$summary
		);
	}

	/**
	 * @param string $UUID
	 * @return string
	 */
	public function flowUUIDToTitleText( string $UUID ): string {
		$UUID = UUID::create( $UUID );
		$collection = PostCollection::newFromId( $UUID );
		$revision = $collection->getLastRevision();

		return $revision->getContent( 'topic-title-plaintext' );
	}
}
