<?php

declare( strict_types = 1 );

namespace Miraheze\DiscordNotifications\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Moderation\Hook\ModerationPendingHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use Miraheze\DiscordNotifications\DiscordNotifier;

class ModerationHooks implements ModerationPendingHook {
	private readonly Config $config;

	public function __construct(
		ConfigFactory $configFactory,
		private readonly DiscordNotifier $discordNotifier,
		private readonly TitleFactory $titleFactory,
		private readonly UserFactory $userFactory,
	) {
		$this->config = $configFactory->makeConfig( 'DiscordNotifications' );
	}

	/** @inheritDoc */
	public function onModerationPending( array $fields, $modid ): void {
		if ( !$this->config->get( 'DiscordNotificationEnabledActions' )['ModerationPending'] ) {
			return;
		}

		$user = $this->userFactory->newFromId( $fields['mod_user'] );
		$pageTitle = $this->titleFactory->newFromTextThrow( $fields['mod_title'] );
		$moderationURL = $this->titleFactory->newFromText( 'Special:Moderation' )->getFullURL();

		$previewLinkEnabled = $this->config->get( 'ModerationPreviewLink' );

		$message = $this->discordNotifier->getMessage( 'discordnotifications-moderation-pending',
			$this->discordNotifier->getDiscordUserText( $user ),
			$this->discordNotifier->getDiscordModerationTitleText( $pageTitle, $modid, $previewLinkEnabled ),
			$moderationURL
		);

		$message .= ' (' . $this->discordNotifier->getMessage( 'discordnotifications-bytes',
				sprintf( '%+d', $fields['mod_new_len'] - $fields['mod_old_len'] ) ) . ')';

		$this->discordNotifier->notify( $message, $user, 'moderation_pending' );
	}
}
