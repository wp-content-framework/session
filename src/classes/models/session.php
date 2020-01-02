<?php
/**
 * WP_Framework_Session Classes Models Session
 *
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

namespace WP_Framework_Session\Classes\Models;

use WP_Framework_Core\Traits\Hook;
use WP_Framework_Core\Traits\Singleton;
use WP_Framework_Session\Traits\Package;

if ( ! defined( 'WP_CONTENT_FRAMEWORK' ) ) {
	exit;
}

/**
 * Class Session
 * @package WP_Framework_Session\Classes\Models
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class Session implements \WP_Framework_Core\Interfaces\Singleton, \WP_Framework_Core\Interfaces\Hook {

	use Singleton, Hook, Package;

	/**
	 * @var bool $session_initialized
	 */
	private static $session_initialized = false;

	/**
	 * @var bool $is_valid_session
	 */
	private static $is_valid_session = false;

	/**
	 * @var bool $session_regenerated
	 */
	private static $session_regenerated = false;

	/**
	 * initialize
	 */
	protected function initialize() {
		if ( ! self::$session_initialized ) {
			self::$session_initialized = true;
			$this->check_session();
		}
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function get_session_key( $key ) {
		return $this->apply_filters( 'session_key', $this->get_slug( 'session_name', '-session' ) ) . '-' . $key;
	}

	/**
	 * @return string
	 */
	private function get_user_check_name() {
		return $this->apply_filters( 'session_user_name', 'user_check' );
	}

	/**
	 * check
	 */
	private function check_session() {
		if ( ! isset( $_SESSION ) && ! headers_sent() ) {
			@session_start(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( isset( $_SESSION ) ) {
			self::$is_valid_session = true;
		}
		$this->security_process();
	}

	/**
	 * security
	 */
	private function security_process() {
		$check = $this->get( $this->get_user_check_name() );
		if ( ! isset( $check ) ) {
			$this->set( $this->get_user_check_name(), $this->app->user->user_id );
		} else {
			if ( (string) $check !== (string) $this->app->user->user_id ) {
				// prevent session fixation
				$this->regenerate();
				$this->set( $this->get_user_check_name(), $this->app->user->user_id );
			}
		}
	}

	/**
	 * regenerate
	 */
	public function regenerate() {
		if ( self::$is_valid_session ) {
			if ( ! self::$session_regenerated ) {
				self::$session_regenerated = true;
				session_regenerate_id( true );
			}
		}
	}

	/**
	 * destroy
	 */
	public function destroy() {
		if ( self::$is_valid_session ) {
			$_SESSION = [];
			setcookie( session_name(), '', time() - 1, '/' );
			session_destroy();
			self::$is_valid_session = false;
		}
	}

	/**
	 * @param mixed $data
	 *
	 * @return bool
	 */
	private function expired_internal( $data ) {
		if ( ! isset( $data['expire'] ) ) {
			return false;
		}

		return $data['expire'] < time();
	}

	/**
	 * @param string $key
	 * @param mixed $data
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	private function get_internal( $key, $data, $default ) {
		if ( ! is_array( $data ) || ! array_key_exists( 'value', $data ) ) {
			return $default;
		}
		if ( $this->expired_internal( $data ) ) {
			$this->delete_internal( $key );

			return $default;
		}

		return $data['value'];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int|null $duration
	 */
	private function set_internal( $key, $value, $duration = null ) {
		$data = [
			'value' => $value,
		];
		if ( isset( $duration ) && $duration > 0 ) {
			$data['expire'] = time() + $duration;
		}
		$_SESSION[ $key ] = $data;
	}

	/**
	 * @param string $key
	 */
	private function delete_internal( $key ) {
		unset( $_SESSION[ $key ] );
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function expired( $key ) {
		if ( ! self::$is_valid_session ) {
			return false;
		}
		$key = $this->get_session_key( $key );
		if ( ! array_key_exists( $key, $_SESSION ) ) {
			return false;
		}

		return $this->expired_internal( $_SESSION[ $key ] );
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( ! self::$is_valid_session ) {
			return $default;
		}
		$key = $this->get_session_key( $key );
		if ( array_key_exists( $key, $_SESSION ) ) {
			return $this->get_internal( $key, $_SESSION[ $key ], $default );
		}

		return $default;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int|null $duration
	 */
	public function set( $key, $value, $duration = null ) {
		if ( ! self::$is_valid_session ) {
			return;
		}
		$key = $this->get_session_key( $key );
		$this->set_internal( $key, $value, $duration );
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function exists( $key ) {
		if ( ! self::$is_valid_session ) {
			return false;
		}

		$key = $this->get_session_key( $key );
		if ( ! array_key_exists( $key, $_SESSION ) ) {
			return false;
		}

		return ! $this->expired_internal( $_SESSION[ $key ] );
	}

	/**
	 * @param string $key
	 */
	public function delete( $key ) {
		if ( ! self::$is_valid_session ) {
			return;
		}
		$key = $this->get_session_key( $key );
		if ( array_key_exists( $key, $_SESSION ) ) {
			$this->delete_internal( $key );
		}
	}
}
