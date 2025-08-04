<?php

declare( strict_types = 1 );

namespace Miraheze\DiscordNotifications;

class DiscordEmbedBuilder {

	/**
	 * The title of the embed.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * The description of the embed.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The color of the embed.
	 *
	 * @var string
	 */
	private string $color;

	/**
	 * The username to display with the embed.
	 *
	 * @var string
	 */
	private string $username;

	/**
	 * The URL of the embed.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * The URL of the avatar to display with the embed.
	 *
	 * @var string
	 */
	private string $avatarUrl;

	/**
	 * The timestamp of the embed.
	 *
	 * @var string
	 */
	private string $timestamp;

	/**
	 * The image of the embed.
	 *
	 * @var array
	 */
	private array $image;

	/**
	 * The thumbnail of the embed.
	 *
	 * @var array
	 */
	private array $thumbnail;

	/**
	 * The author of the embed.
	 *
	 * @var array
	 */
	private array $author;

	/**
	 * The footer of the embed.
	 *
	 * @var array
	 */
	private array $footer;

	/**
	 * The fields of the embed.
	 *
	 * @var array
	 */
	private $fields = [];

	/**
	 * Sets the title of the embed.
	 *
	 * @param string $title The title to set.
	 * @return self
	 */
	public function setTitle( string $title ): self {
		$this->title = $title;

		return $this;
	}

	/**
	 * Sets the description of the embed.
	 *
	 * @param string $description The description to set.
	 * @return self
	 */
	public function setDescription( string $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Sets the color of the embed.
	 *
	 * @param string $color The color to set, in hexadecimal format.
	 * @return self
	 */
	public function setColor( string $color ): self {
		$this->color = $color;

		return $this;
	}

	/**
	 * Sets the timestamp of the embed.
	 *
	 * @param string $timestamp The timestamp to set.
	 * @return self
	 */
	public function setTimestamp( string $timestamp ): self {
		$this->timestamp = $timestamp;

		return $this;
	}

	/**
	 * Sets the username to display with the embed.
	 *
	 * @param string $username The username to set.
	 * @return self
	 */
	public function setUsername( string $username ): self {
		$this->username = $username;

		return $this;
	}

	/**
	 * Sets the URL of the embed.
	 *
	 * @param string $url The URL to set.
	 * @return self
	 */
	public function setUrl( string $url ): self {
		$this->url = $url;

		return $this;
	}

	/**
	 * Sets the URL of the avatar to display with the embed.
	 *
	 * @param string $avatarUrl The URL of the avatar to set.
	 * @return self
	 */
	public function setAvatarUrl( string $avatarUrl ): self {
		$this->avatarUrl = $avatarUrl;

		return $this;
	}

	/**
	 * Sets the image of the embed.
	 *
	 * @param string $url The URL of the image to set.
	 * @return self
	 */
	public function setImage( string $url ): self {
		$this->image = [
			'url' => $url,
		];

		return $this;
	}

	/**
	 * Sets the thumbnail of the embed.
	 *
	 * @param string $url The URL of the thumbnail to set.
	 * @return self
	 */
	public function setThumbnail( string $url ): self {
		$this->thumbnail = [
			'url' => $url,
		];

		return $this;
	}

	/**
	 * Sets the author of the embed.
	 *
	 * @param string $name The author name to set.
	 * @param string $url The author URL to set.
	 * @param string $iconUrl The author icon URL to set.
	 * @return self
	 */
	public function setAuthor( string $name, string $url = '', string $iconUrl = '' ): self {
		$this->author = array_filter( [
			'name' => $name,
			'url' => $url,
			'icon_url' => $iconUrl,
		] );

		return $this;
	}

	/**
	 * Sets the footer text and icon URL (if supplied) of the embed.
	 *
	 * @param string $text The text of the footer to set.
	 * @param string $iconUrl The URL of the footer icon to set.
	 * @return self
	 */
	public function setFooter( string $text, string $iconUrl = '' ): self {
		$this->footer = array_filter( [
			'text' => $text,
			'icon_url' => $iconUrl,
		] );

		return $this;
	}

	/**
	 * Adds a field to the embed.
	 *
	 * @param string $name The name of the field.
	 * @param string $value The value of the field.
	 * @param bool $inline Whether the field should be inline or not.
	 * @return self
	 */
	public function addField( string $name, string $value, bool $inline = false ): self {
		$this->fields[] = [
			'name' => $name,
			'value' => $value,
			'inline' => $inline,
		];

		return $this;
	}

	/**
	 * Builds and returns the JSON representation of the embed.
	 *
	 * @return string
	 */
	public function build(): string {
		$embed = [
			'embeds' => [
				array_filter( [
					'title' => $this->title,
					'author' => $this->author,
					'image' => $this->image,
					'thumbnail' => $this->thumbnail,
					'description' => $this->description,
					'url' => $this->url,
					'color' => $this->color,
					'timestamp' => $this->timestamp,
					'fields' => $this->fields,
					'footer' => $this->footer,
				] ),
			],
			'username' => $this->username,
		];

		if ( $this->avatarUrl ) {
			$embed['avatar_url'] = $this->avatarUrl;
		}

		return json_encode( $embed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
