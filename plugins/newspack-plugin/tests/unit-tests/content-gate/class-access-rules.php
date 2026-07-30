<?php
/**
 * Tests the Access Rules class with group subscription support.
 *
 * @package Newspack\Tests
 */

use Newspack\Access_Rules;
use Newspack\Group_Subscription;
use Newspack\Reader_Activation;
use Newspack\WooCommerce_Connection;

/**
 * Test Access Rules functionality.
 *
 * @group Access_Rules
 */
class Newspack_Test_Access_Rules extends WP_UnitTestCase {
	/**
	 * Test user ID for the subscription owner.
	 *
	 * @var int
	 */
	private static $owner_user_id;

	/**
	 * Test user ID for a group member.
	 *
	 * @var int
	 */
	private static $member_user_id;

	/**
	 * Test user ID for a non-member.
	 *
	 * @var int
	 */
	private static $non_member_user_id;

	/**
	 * Test subscription ID.
	 *
	 * @var int
	 */
	private static $subscription_id = 100;

	/**
	 * Test product ID.
	 *
	 * @var int
	 */
	private static $product_id = 50;

	/**
	 * Set up test fixtures.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Include WC mocks.
		require_once dirname( __DIR__, 2 ) . '/mocks/wc-mocks.php';
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		// Reset the subscriptions and products databases.
		global $subscriptions_database, $products_database;
		$subscriptions_database = [];
		$products_database      = [];

		// Create test users.
		self::$owner_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		self::$member_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		self::$non_member_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		// Mark users as readers.
		update_user_meta( self::$owner_user_id, 'np_reader', true );
		update_user_meta( self::$member_user_id, 'np_reader', true );
		update_user_meta( self::$non_member_user_id, 'np_reader', true );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		parent::tear_down();

		// Clean up user meta.
		delete_user_meta( self::$member_user_id, Group_Subscription::GROUP_SUBSCRIPTION_USER_META_KEY );
	}

	/**
	 * Helper to create a test subscription.
	 *
	 * @param array $args Subscription arguments.
	 * @return WC_Subscription
	 */
	private function create_subscription( $args = [] ) {
		$defaults = [
			'id'               => self::$subscription_id,
			'customer_id'      => self::$owner_user_id,
			'status'           => 'active',
			'total'            => 10,
			'billing_period'   => 'month',
			'billing_interval' => 1,
			'products'         => [ self::$product_id ],
			'dates'            => [
				'start' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ),
			],
		];

		return wcs_create_subscription( array_merge( $defaults, $args ) );
	}

	/**
	 * Helper to enable group subscription for a subscription.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 */
	private function enable_group_subscription( $subscription ) {
		$subscription->update_meta_data( '_newspack_group_subscription_enabled', 'yes' );
		$subscription->update_meta_data( '_newspack_group_subscription_limit', 10 );
	}

	/**
	 * Helper to add a user as a group member.
	 *
	 * @param int $user_id The user ID.
	 * @param int $subscription_id The subscription ID.
	 */
	private function add_group_member( $user_id, $subscription_id ) {
		add_user_meta( $user_id, Group_Subscription::GROUP_SUBSCRIPTION_USER_META_KEY, $subscription_id );
	}

	/**
	 * Test that subscription owner has access via their own subscription.
	 */
	public function test_owner_has_access_via_own_subscription() {
		$subscription = $this->create_subscription();

		$has_access = Access_Rules::has_active_subscription( self::$owner_user_id, [ self::$product_id ] );

		$this->assertTrue( $has_access, 'Subscription owner should have access via their own subscription.' );
	}

	/**
	 * Test that group member has access via group subscription.
	 */
	public function test_group_member_has_access_via_group_subscription() {
		$subscription = $this->create_subscription();
		$this->enable_group_subscription( $subscription );
		$this->add_group_member( self::$member_user_id, $subscription->get_id() );

		$has_access = Access_Rules::has_active_subscription( self::$member_user_id, [ self::$product_id ] );

		$this->assertTrue( $has_access, 'Group member should have access via group subscription.' );
	}

	/**
	 * Test that non-member does not have access.
	 */
	public function test_non_member_does_not_have_access() {
		$subscription = $this->create_subscription();
		$this->enable_group_subscription( $subscription );

		$has_access = Access_Rules::has_active_subscription( self::$non_member_user_id, [ self::$product_id ] );

		$this->assertFalse( $has_access, 'Non-member should not have access.' );
	}

	/**
	 * Test that group member does not have access if subscription is inactive.
	 */
	public function test_group_member_no_access_if_subscription_inactive() {
		$subscription = $this->create_subscription( [ 'status' => 'cancelled' ] );
		$this->enable_group_subscription( $subscription );
		$this->add_group_member( self::$member_user_id, $subscription->get_id() );

		$has_access = Access_Rules::has_active_subscription( self::$member_user_id, [ self::$product_id ] );

		$this->assertFalse( $has_access, 'Group member should not have access if subscription is inactive.' );
	}

	/**
	 * Test that group member does not have access if subscription has wrong product.
	 */
	public function test_group_member_no_access_if_wrong_product() {
		$subscription = $this->create_subscription( [ 'products' => [ 999 ] ] );
		$this->enable_group_subscription( $subscription );
		$this->add_group_member( self::$member_user_id, $subscription->get_id() );

		$has_access = Access_Rules::has_active_subscription( self::$member_user_id, [ self::$product_id ] );

		$this->assertFalse( $has_access, 'Group member should not have access if subscription has wrong product.' );
	}

	/**
	 * Test that group member has access with empty product filter (any subscription).
	 */
	public function test_group_member_has_access_with_empty_product_filter() {
		$subscription = $this->create_subscription();
		$this->enable_group_subscription( $subscription );
		$this->add_group_member( self::$member_user_id, $subscription->get_id() );

		$has_access = Access_Rules::has_active_subscription( self::$member_user_id, [] );

		$this->assertTrue( $has_access, 'Group member should have access when no product filter is specified.' );
	}

	/**
	 * Test evaluate_rules passes user_id to rule callbacks.
	 */
	public function test_evaluate_rules_with_explicit_user_id() {
		// Register a simple test rule that checks user meta.
		Access_Rules::register_rule(
			[
				'id'       => 'test_meta_rule',
				'name'     => 'Test meta rule',
				'callback' => function( $user_id, $args ) {
					return (bool) get_user_meta( $user_id, $args, true );
				},
			]
		);

		// Set meta on member but not on non-member.
		update_user_meta( self::$member_user_id, 'test_gate_pass', '1' );

		$rules = [
			[
				[
					'slug'  => 'test_meta_rule',
					'value' => 'test_gate_pass',
				],
			],
		];

		// Member should pass.
		$this->assertTrue(
			Access_Rules::evaluate_rules( $rules, self::$member_user_id ),
			'User with matching meta should pass evaluate_rules.'
		);

		// Non-member should fail.
		$this->assertFalse(
			Access_Rules::evaluate_rules( $rules, self::$non_member_user_id ),
			'User without matching meta should fail evaluate_rules.'
		);
	}

	/**
	 * Test evaluate_rules defaults to current user when no user_id is passed.
	 */
	public function test_evaluate_rules_defaults_to_current_user() {
		Access_Rules::register_rule(
			[
				'id'       => 'test_current_user_rule',
				'name'     => 'Test current user rule',
				'callback' => function( $user_id, $args ) {
					return $user_id === (int) $args;
				},
			]
		);

		wp_set_current_user( self::$member_user_id );

		$rules = [
			[
				[
					'slug'  => 'test_current_user_rule',
					'value' => (string) self::$member_user_id,
				],
			],
		];

		// Should pass using current user (no user_id argument).
		$this->assertTrue(
			Access_Rules::evaluate_rules( $rules ),
			'evaluate_rules should default to current user when no user_id is passed.'
		);
	}

	/**
	 * Test pending-cancel status still grants access.
	 */
	public function test_pending_cancel_status_grants_access() {
		$subscription = $this->create_subscription( [ 'status' => 'pending-cancel' ] );
		$this->enable_group_subscription( $subscription );
		$this->add_group_member( self::$member_user_id, $subscription->get_id() );

		$has_access = Access_Rules::has_active_subscription( self::$member_user_id, [ self::$product_id ] );

		$this->assertTrue( $has_access, 'Group member should have access with pending-cancel subscription.' );
	}

	// =========================================================================
	// evaluate_rules() with explicit $user_id — via built-in subscription rule
	// =========================================================================

	/**
	 * Test that evaluate_rules() routes to the correct user when an explicit
	 * $user_id is passed, using the built-in subscription rule type.
	 * (Complements the custom-callback variant in test_evaluate_rules_with_explicit_user_id.)
	 */
	public function test_evaluate_rules_respects_explicit_user_id() {
		$this->create_subscription();

		$access_rules = [
			[
				[
					'slug'  => 'subscription',
					'value' => [ self::$product_id ],
				],
			],
		];

		$this->assertTrue(
			Access_Rules::evaluate_rules( $access_rules, self::$owner_user_id ),
			'evaluate_rules should return true for the subscription owner when called with their user ID.'
		);

		$this->assertFalse(
			Access_Rules::evaluate_rules( $access_rules, self::$non_member_user_id ),
			'evaluate_rules should return false for a non-member when called with their user ID.'
		);
	}

	/**
	 * Test that evaluate_rules() falls back to the current user when $user_id
	 * is null, using the built-in subscription rule type.
	 * (Complements the custom-callback variant in test_evaluate_rules_defaults_to_current_user.)
	 */
	public function test_evaluate_rules_defaults_to_current_user_when_user_id_is_null() {
		$this->create_subscription();

		$access_rules = [
			[
				[
					'slug'  => 'subscription',
					'value' => [ self::$product_id ],
				],
			],
		];

		wp_set_current_user( self::$owner_user_id );
		$this->assertTrue(
			Access_Rules::evaluate_rules( $access_rules, null ),
			'evaluate_rules should return true for the subscription owner when they are the current user.'
		);

		wp_set_current_user( self::$non_member_user_id );
		$this->assertFalse(
			Access_Rules::evaluate_rules( $access_rules, null ),
			'evaluate_rules should return false for a non-member when they are the current user.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Create a real `product_variation` post, the way WooCommerce stores one: the generated
	 * title in `post_title` and the attribute summary in `post_excerpt`.
	 *
	 * The variation options are read from the post rows rather than from hydrated products,
	 * so these have to be real posts for the tests to exercise the query that ships.
	 *
	 * @param int    $parent_id The variable subscription's product ID.
	 * @param string $title     The variation's generated title.
	 * @param string $summary   The attribute summary, if any.
	 * @param string $status    The post status.
	 *
	 * @return int The variation post ID.
	 */
	private function create_variation_post( $parent_id, $title, $summary = '', $status = 'publish' ) {
		return $this->factory->post->create(
			[
				'post_type'    => 'product_variation',
				'post_parent'  => $parent_id,
				'post_title'   => $title,
				'post_excerpt' => $summary,
				'post_status'  => $status,
			]
		);
	}

	/**
	 * A variable subscription's variations are selectable in their own right, so a gate can
	 * require one tier of it without requiring the others.
	 *
	 * The rule already evaluates variation IDs — `WC_Subscription::has_product()` matches a
	 * line item's `variation_id` as well as its `product_id` — so leaving them out of the
	 * options made a rule the system honours impossible to configure, or to read back once
	 * migrated data had put one in a gate.
	 *
	 * @group Access_Rules
	 */
	public function test_get_subscription_products_options_includes_variations() {
		wc_create_mock_product(
			[
				'id'   => 900,
				'type' => 'subscription',
				'name' => 'Supporter',
			]
		);
		wc_create_mock_product(
			[
				'id'   => 901,
				'type' => 'variable-subscription',
				'name' => 'Membership',
			]
		);
		$monthly_variation_id = $this->create_variation_post( 901, 'Membership - Monthly' );
		$annual_variation_id  = $this->create_variation_post( 901, 'Membership - Annual' );

		$options_by_value = array_column( Access_Rules::get_subscription_products_options(), 'label', 'value' );

		$this->assertSame(
			[
				900                   => 'Supporter',
				901                   => 'Membership',
				$monthly_variation_id => 'Membership - Monthly',
				$annual_variation_id  => 'Membership - Annual',
			],
			$options_by_value,
			'Options should list simple subscriptions, variable subscription parents, and each parent\'s variations.'
		);
	}

	/**
	 * A private variation is listed, a draft one is not.
	 *
	 * A publisher can hide a tier without the readers still paying for it losing their
	 * subscription, so a rule has to be able to name it. A draft variation has never been
	 * purchasable, so listing it would only offer a rule that matches nothing.
	 *
	 * @group Access_Rules
	 */
	public function test_get_subscription_products_options_includes_private_variations_only() {
		wc_create_mock_product(
			[
				'id'   => 910,
				'type' => 'variable-subscription',
				'name' => 'Membership',
			]
		);
		$private_variation_id = $this->create_variation_post( 910, 'Membership - Retired', '', 'private' );
		$this->create_variation_post( 910, 'Membership - Draft', '', 'draft' );

		$values = array_column( Access_Rules::get_subscription_products_options(), 'value' );

		$this->assertSame( [ 910, $private_variation_id ], $values, 'A private variation should be listed; a draft one should not.' );
	}

	/**
	 * A variation belonging to a product that is not a variable subscription is not listed,
	 * so an unrelated variable product's tiers cannot leak into the subscription rule.
	 *
	 * @group Access_Rules
	 */
	public function test_get_subscription_products_options_ignores_non_subscription_variations() {
		wc_create_mock_product(
			[
				'id'   => 915,
				'type' => 'variable',
				'name' => 'Tote bag',
			]
		);
		$this->create_variation_post( 915, 'Tote bag - Large' );

		$values = array_column( Access_Rules::get_subscription_products_options(), 'value' );

		$this->assertSame( [], $values, 'A plain variable product and its variations should not be listed.' );
	}

	/**
	 * WooCommerce drops the attribute suffix from a variation's generated title when the
	 * parent carries three or more attributes (or two or more where an attribute name is
	 * multi-word), leaving the variation titled exactly like its parent.
	 *
	 * A picker listing "Membership" four times tells a publisher nothing about which tier
	 * each entry is, so recover the attributes from the variation's summary. Where there is
	 * no summary to recover, the bare parent title stands: the pickers render every option
	 * as `<name> (#<id>)`, so the entries stay individually selectable either way.
	 *
	 * @group Access_Rules
	 */
	public function test_get_subscription_products_options_names_variations_titled_like_their_parent() {
		wc_create_mock_product(
			[
				'id'   => 920,
				'type' => 'variable-subscription',
				'name' => 'Membership',
			]
		);
		// Titled exactly like the parent, but carrying an attribute summary.
		$monthly_variation_id = $this->create_variation_post( 920, 'Membership', 'Term: Monthly' );
		$annual_variation_id  = $this->create_variation_post( 920, 'Membership', 'Term: Annual' );
		// Titled like the parent with no attribute summary to fall back on.
		$bare_variation_id = $this->create_variation_post( 920, 'Membership' );

		$options_by_value = array_column( Access_Rules::get_subscription_products_options(), 'label', 'value' );

		$this->assertSame(
			[
				920                   => 'Membership',
				$monthly_variation_id => 'Membership - Term: Monthly',
				$annual_variation_id  => 'Membership - Term: Annual',
				// No attribute summary to recover, so the generated title stands.
				$bare_variation_id    => 'Membership',
			],
			$options_by_value,
			'A variation titled like its parent should take its attribute summary where it has one.'
		);
	}
}
