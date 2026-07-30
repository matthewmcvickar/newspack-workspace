<?php
/**
 * Newspack Content Gate Access Rules
 *
 * @package Newspack
 */

namespace Newspack;

use Newspack\WooCommerce_Connection;

/**
 * Main class.
 */
class Access_Rules {

	const META_KEY = 'access_rules';

	/**
	 * Registered rules.
	 *
	 * @var array
	 */
	private static $rules = [];

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_default_rules' ] );
	}
	/**
	 * Register a rule.
	 *
	 * @param array $config {
	 *     The rule configuration.
	 *
	 *     @type string   $id                 The rule ID.
	 *     @type string   $name               The rule name.
	 *     @type string   $description        The rule description.
	 *     @type mixed    $default            The rule default value.
	 *     @type array    $options            The rule options.
	 *     @type callable $callback           The rule callback.
	 *     @type bool     $is_boolean         Whether the rule is a boolean rule.
	 *     @type bool     $supports_anonymous Whether the rule's callback can evaluate access for
	 *                                        a logged-out visitor (`user_id = 0`). Defaults to
	 *                                        false — `evaluate_rule` short-circuits to false for
	 *                                        anonymous users on rules that don't opt in. Rules
	 *                                        that opt in are responsible for cache-safety
	 *                                        (e.g. only running per-IP logic when the page is
	 *                                        already uncached).
	 * }
	 *
	 * @return void|\WP_Error
	 */
	public static function register_rule( $config ) {
		if ( ! isset( $config['id'] ) ) {
			return new \WP_Error( 'invalid_rule_id', __( 'Rule ID is required.', 'newspack' ) );
		}
		if ( isset( self::$rules[ $config['id'] ] ) ) {
			return new \WP_Error( 'rule_already_registered', __( 'Rule already registered.', 'newspack' ) );
		}
		if ( ! isset( $config['callback'] ) ) {
			return new \WP_Error( 'invalid_rule_callback', __( 'Rule callback is required.', 'newspack' ) );
		}
		if ( ! is_callable( $config['callback'] ) ) {
			return new \WP_Error( 'invalid_rule_callback', __( 'Rule callback is not callable.', 'newspack' ) );
		}
		$rule = wp_parse_args(
			$config,
			[
				'name'        => ucwords( str_replace( '_', ' ', $config['id'] ) ),
				'description' => '',
				'default'     => ! empty( $config['options'] ) ? [] : '',
				'options'     => [],
				'is_boolean'  => false,
			]
		);
		self::$rules[ $rule['id'] ] = $rule;
	}

	/**
	 * Get all registered rules.
	 *
	 * @return array The registered rules.
	 */
	public static function get_registered_rules() {
		return self::$rules;
	}

	/**
	 * Register the default access rules.
	 */
	public static function register_default_rules() {
		$rules = [
			'subscription' => [
				'name'        => __( 'Active subscription', 'newspack-plugin' ),
				'description' => __( 'Requires an active subscription to selected products.', 'newspack-plugin' ),
				'options'     => [ __CLASS__, 'get_subscription_products_options' ],
				'callback'    => [ __CLASS__, 'has_active_subscription' ],
			],
			'email_domain' => [
				'name'        => __( 'Whitelisted email domain', 'newspack-plugin' ),
				'description' => __( 'Only allow readers with specific email domains.', 'newspack-plugin' ),
				'placeholder' => __( 'example.com,another.com', 'newspack-plugin' ),
				'callback'    => [ __CLASS__, 'is_email_domain_whitelisted' ],
			],
			'reader_data'  => [
				'name'        => __( 'Reader data', 'newspack-plugin' ),
				'description' => __( 'Set custom conditions based on reader data key/value pairs.', 'newspack-plugin' ),
				'callback'    => [ __CLASS__, 'has_reader_data' ],
			],
			'institution'  => [
				'name'               => __( 'Institutional access', 'newspack-plugin' ),
				'description'        => __( 'Grant access to readers from selected institutions.', 'newspack-plugin' ),
				'options'            => [ Institution::class, 'get_options' ],
				'callback'           => [ Institution::class, 'evaluate' ],
				'supports_anonymous' => true,
			],
		];

		foreach ( $rules as $id => $rule ) {
			self::register_rule( array_merge( $rule, [ 'id' => $id ] ) );
		}
	}

	/**
	 * Get access rules.
	 *
	 * @return array The access rules.
	 */
	public static function get_access_rules() {
		return array_map(
			function( $rule ) {
				if ( ! empty( $rule['options'] ) && is_callable( $rule['options'] ) ) {
					$rule['options'] = call_user_func( $rule['options'] );
				}
				return $rule;
			},
			self::$rules
		);
	}

	/**
	 * Get the access rule by slug.
	 *
	 * @param string $slug Rule slug.
	 *
	 * @return array|null Rule config or null if not found.
	 */
	public static function get_rule( $slug ) {
		return self::$rules[ $slug ] ?? null;
	}

	/**
	 * Evaluate whether the given or current user can bypass the given access rule.
	 *
	 * @param string   $rule_slug Access rule slug.
	 * @param mixed    $args      Additional arguments for the access rule callback.
	 * @param int|null $user_id   User ID. If not given, checks the current user.
	 *
	 * @return bool
	 */
	public static function evaluate_rule( $rule_slug, $args = null, $user_id = null ) {
		$rule = self::get_rule( $rule_slug );

		// Rule doesn't exist or lacks a callback function to execute, don't block access for it.
		if ( empty( $rule['callback'] ) ) {
			return true;
		}

		// If evaluating for the current user, they must be logged in (unless the rule supports anonymous evaluation).
		$user_id = $user_id ?? \get_current_user_id();
		if ( ! $user_id && empty( $rule['supports_anonymous'] ) ) {
			return false;
		}

		// Access rule must have a callable callback function.
		if ( ! is_callable( $rule['callback'] ) ) {
			return false;
		}

		return call_user_func( $rule['callback'], $user_id, $args );
	}

	/**
	 * Determine whether the gate's custom_access rules grant access to an
	 * anonymous (logged-out) visitor.
	 *
	 * Only rules that (a) declare `supports_anonymous` and (b) have a populated
	 * `value` are considered. An unpopulated rule is treated as "not configured"
	 * rather than "matches everyone" — Access_Rules's underlying evaluators
	 * return true for empty values as the rule's own no-constraint semantics,
	 * which is correct for the rule in isolation but must not silently bypass
	 * registration here.
	 *
	 * Groups containing any non-eligible rule are dropped (the AND-within-group
	 * semantics would force the group to fail for an anonymous visitor anyway,
	 * since non-anonymous rules return false for `user_id = 0`).
	 *
	 * @param array $access_rules Custom access rules in grouped or flat format.
	 *
	 * @return bool True if a populated, anonymous-capable rule grants access.
	 */
	public static function evaluate_anonymous_rules( $access_rules ) {
		if ( empty( $access_rules ) ) {
			return false;
		}
		$eligible_groups = [];
		foreach ( self::normalize_rules( $access_rules ) as $group ) {
			if ( empty( $group ) || ! is_array( $group ) ) {
				continue;
			}
			$group_eligible = true;
			foreach ( $group as $rule ) {
				// `empty()` is acceptable for `value` while the only `supports_anonymous` rule
				// (`institution`) stores an array of post IDs — empty array means "no institutions
				// selected." If a future anonymous-capable rule uses a falsy-but-valid scalar (e.g.
				// `0`, `'0'`, `false`), tighten this check accordingly.
				if ( ! isset( $rule['slug'] ) || empty( $rule['value'] ) ) {
					$group_eligible = false;
					break;
				}
				$rule_def = self::get_rule( $rule['slug'] );
				if ( empty( $rule_def['supports_anonymous'] ) ) {
					$group_eligible = false;
					break;
				}
			}
			if ( $group_eligible ) {
				$eligible_groups[] = $group;
			}
		}
		if ( empty( $eligible_groups ) ) {
			return false;
		}
		return self::evaluate_rules( $eligible_groups, 0 );
	}

	/**
	 * Evaluate access rules with OR logic between groups and AND logic within groups.
	 *
	 * Rules structure: [ [ rule1, rule2 ], [ rule3, rule4 ] ]
	 * - Groups use OR logic: reader must pass at least one group
	 * - Rules within a group use AND logic: reader must pass all rules in the group
	 *
	 * @param array $access_rules The access rules (array of groups, each group is an array of rules).
	 * @param int   $user_id     Optional. User ID to evaluate rules for. Defaults to current user.
	 *
	 * @return bool True if access is granted, false if restricted.
	 */
	public static function evaluate_rules( $access_rules, $user_id = null ) {
		if ( empty( $access_rules ) ) {
			return true;
		}

		// Normalize legacy flat rules structure to grouped format.
		$access_rules = self::normalize_rules( $access_rules );

		// Evaluate each group with OR logic - if any group passes, grant access.
		foreach ( $access_rules as $group ) {
			if ( self::evaluate_rules_group( $group, $user_id ) ) {
				return true;
			}
		}

		// No group passed - restrict access.
		return false;
	}

	/**
	 * Evaluate a single group of access rules with AND logic.
	 *
	 * @param array $group   Array of rules in the group.
	 * @param int   $user_id Optional. User ID to evaluate rules for. Defaults to current user.
	 *
	 * @return bool True if all rules in the group pass, false otherwise.
	 */
	private static function evaluate_rules_group( $group, $user_id = null ) {
		if ( empty( $group ) || ! is_array( $group ) ) {
			return true;
		}

		foreach ( $group as $rule ) {
			if ( ! isset( $rule['slug'] ) ) {
				continue;
			}
			if ( ! self::evaluate_rule( $rule['slug'], $rule['value'] ?? null, $user_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize access rules to grouped format.
	 *
	 * Converts flat rules [ rule1, rule2 ] to grouped format [ [ rule1 ], [ rule2 ] ],
	 * where each rule is its own group (OR logic). Already grouped rules are left as-is.
	 *
	 * @param array $access_rules The access rules.
	 *
	 * @return array Normalized access rules in grouped format.
	 */
	public static function normalize_rules( $access_rules ) {
		if ( empty( $access_rules ) ) {
			return [];
		}

		// Check if already in grouped format (array of arrays with rules).
		// A grouped format has arrays as first-level elements.
		// A flat format has rule objects (with 'slug' key) as first-level elements.
		$first_element = reset( $access_rules );
		if ( is_array( $first_element ) && ! isset( $first_element['slug'] ) ) {
			// Already in grouped format.
			return $access_rules;
		}

		// Convert flat format to OR logic: each rule becomes its own group.
		return array_map(
			function ( $rule ) {
				return [ $rule ];
			},
			$access_rules
		);
	}

	/**
	 * Get subscriptions eligible for access rules.
	 *
	 * Variations of a variable subscription are listed alongside their parent, because the
	 * rule evaluates them: `WC_Subscription::has_product()` matches a line item's
	 * `variation_id` as well as its `product_id`. Selecting the parent therefore grants
	 * access to subscribers of any of its variations, while selecting a single variation
	 * narrows the rule to that one — gating on the annual tier of a variable subscription
	 * but not the monthly, say. Without the variations listed, that narrower rule can be
	 * held by a gate (migrated data carries variation IDs) but never configured or read
	 * back in the editor.
	 *
	 * Unpublished products are listed too — `wc_get_products()` defaults to draft, pending,
	 * private and publish. Unlike institutions, whose options are published-only, a
	 * subscription to a product the publisher has since drafted still evaluates: the line
	 * item is what the rule matches, and it does not stop existing when the product is
	 * unpublished. Hiding those products would make the readers still paying for them
	 * ungateable.
	 *
	 * The list is unbounded and rebuilt per call. See NPPD-2138 for memoizing it — gate
	 * saves rebuild it once per rule.
	 *
	 * @return array Array of [ 'label' => string, 'value' => int ].
	 */
	public static function get_subscription_products_options() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return [];
		}
		$products = \wc_get_products(
			[
				'type'  => [ 'subscription', 'variable-subscription' ],
				'limit' => -1,
			]
		);
		$variations_by_parent = self::get_subscription_variation_posts( $products );
		$options              = [];
		foreach ( $products as $product ) {
			$options[] = [
				'label' => $product->get_name(),
				'value' => $product->get_id(),
			];
			foreach ( $variations_by_parent[ $product->get_id() ] ?? [] as $variation ) {
				$options[] = [
					'label' => self::get_variation_option_label( $product->get_name(), $variation ),
					'value' => $variation->ID,
				];
			}
		}
		return $options;
	}

	/**
	 * Fetch the variation posts of the given variable subscriptions, keyed by parent ID.
	 *
	 * The post row carries everything a label needs — WooCommerce stores a variation's
	 * generated title in `post_title` and its attribute summary in `post_excerpt` — so this
	 * reads the posts directly rather than hydrating a `WC_Product` per variation.
	 * Hydrating would cost a full meta read, a parent re-read and two term lookups each,
	 * and this runs on every admin page load that localises the access rules, including
	 * every block editor load.
	 *
	 * Private variations are included alongside published ones: a reader can hold an active
	 * subscription to a tier the publisher has since hidden, and the rule still has to be
	 * able to name it.
	 *
	 * @param \WC_Product[] $products The subscription products to collect variations for.
	 *
	 * @return array<int, \WP_Post[]> Variation posts keyed by parent product ID.
	 */
	private static function get_subscription_variation_posts( $products ) {
		$parent_ids = [];
		foreach ( $products as $product ) {
			if ( $product->is_type( 'variable-subscription' ) ) {
				$parent_ids[] = $product->get_id();
			}
		}
		if ( empty( $parent_ids ) ) {
			return [];
		}
		$variations = \get_posts(
			[
				'post_type'              => 'product_variation',
				'post_parent__in'        => $parent_ids,
				'post_status'            => [ 'publish', 'private' ],
				'posts_per_page'         => -1,
				'orderby'                => [
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				],
				// Only the title, excerpt, ID and parent are read.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		$by_parent = [];
		foreach ( $variations as $variation ) {
			$by_parent[ $variation->post_parent ][] = $variation;
		}
		return $by_parent;
	}

	/**
	 * Build the option label for a subscription variation.
	 *
	 * WooCommerce generates a variation's title as "Parent - Attribute" and names it that
	 * way throughout its own admin, so that title is the label wherever it is usable. But it
	 * drops the attribute suffix when the parent carries three or more attributes, or two or
	 * more where an attribute name is multi-word — leaving the variation titled exactly like
	 * its parent. Recover the attributes from the summary in that case, so the variation
	 * still reads as the tier it is rather than as a second copy of its parent. The summary
	 * spells out attribute names where the generated title omits them ("Term: Annual" rather
	 * than "Annual"); matching the generated style would mean hydrating the variation, which
	 * costs more than the fallback is worth.
	 *
	 * Labels need not be unique: the pickers render every option as `<name> (#<id>)` and
	 * resolve a token by the ID it carries, so two options sharing a name stay distinct.
	 *
	 * @param string   $parent_name The variable subscription parent's name.
	 * @param \WP_Post $variation   The variation post.
	 *
	 * @return string The option label.
	 */
	private static function get_variation_option_label( string $parent_name, \WP_Post $variation ): string {
		$label = $variation->post_title;
		if ( $label !== $parent_name ) {
			return $label;
		}
		return '' !== $variation->post_excerpt ? sprintf( '%s - %s', $label, $variation->post_excerpt ) : $label;
	}

	/**
	 * Whether the user has an active subscription for one of the given products.
	 * Also checks if the user is a member of a group subscription with the required products.
	 *
	 * Note: `$strict` only constrains the built-in ownership / group-membership checks.
	 * The `newspack_access_rules_has_active_subscription` filter is always applied and
	 * its return value is the final result, so a third-party filter callback can grant
	 * access even when `$strict` is true. Filter authors should opt in to the 4th `$strict`
	 * arg (`accepted_args` >= 4) and respect it — e.g., short-circuit and return
	 * `$has_subscription` unchanged when `$strict` is true and the access claim isn't
	 * strictly an owned subscription. Otherwise callers using `$strict` to distinguish
	 * owner-vs-member access (e.g., `Content_Gate` source labels) may misclassify
	 * filter-granted access as local ownership.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $product_ids Required product IDs.
	 * @param bool  $strict      If true, only consider active subscriptions owned by $user_id (ignore group subscription memberships).
	 * @return bool
	 */
	public static function has_active_subscription( $user_id, $product_ids, $strict = false ) {
		$has_subscription = false;

		// Check user's own subscriptions.
		if ( ! empty( WooCommerce_Connection::get_active_subscriptions_for_user( $user_id, $product_ids ) ) ) {
			$has_subscription = true;
		}

		// Check group subscriptions the user is a member of.
		if ( ! $strict && ! $has_subscription && function_exists( 'wcs_get_subscription' ) ) {
			$group_subscriptions = Group_Subscription::get_group_subscriptions_for_user( $user_id );
			foreach ( $group_subscriptions as $subscription ) {
				if ( ! $subscription || ! $subscription->has_status( WooCommerce_Connection::ACTIVE_SUBSCRIPTION_STATUSES ) ) {
					continue;
				}
				// If no product filter, any active group subscription grants access.
				if ( empty( $product_ids ) ) {
					$has_subscription = true;
					break;
				}
				// Check if the subscription has any of the required products.
				foreach ( $product_ids as $product_id ) {
					if ( $subscription->has_product( $product_id ) ) {
						$has_subscription = true;
						break 2;
					}
				}
			}
		}

		/**
		 * Filters whether a user has an active subscription for the given products.
		 *
		 * @param bool  $has_subscription Whether the user has an active subscription.
		 * @param int   $user_id          User ID.
		 * @param array $product_ids      Required product IDs.
		 * @param bool  $strict           If true, only consider active subscriptions owned by $user_id (ignore group subscription memberships).
		 */
		return apply_filters( 'newspack_access_rules_has_active_subscription', $has_subscription, $user_id, $product_ids, $strict );
	}

	/**
	 * Whether the user’s email address contains one of the given domains.
	 *
	 * @param int    $user_id User ID.
	 * @param string $domains Comma-delimited list of domains.
	 * @return bool
	 */
	public static function is_email_domain_whitelisted( $user_id, $domains ) {
		// If no domains are specified, allow access.
		if ( empty( $domains ) ) {
			return true;
		}
		$domains = str_replace( PHP_EOL, ',', $domains );
		$domains = explode( ',', $domains );
		$domains = array_map( 'trim', $domains );
		$domains = array_map( 'strtolower', $domains );
		$user    = \get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$email = $user->data->user_email;
		if ( ! $email ) {
			return false;
		}
		if ( Reader_Activation::is_reader_verified( $user ) === false ) {
			return false;
		}
		$email_domain = strtolower( substr( $email, strrpos( $email, '@' ) + 1 ) );
		return in_array( $email_domain, $domains, true );
	}

	/**
	 * Determine reader data key-values the reader must have.
	 *
	 * @param int    $user_id User ID.
	 * @param string $data    Key-value pairs separate by semicolon.
	 *
	 * @return bool Whether the reader has the required data.
	 */
	public static function has_reader_data( $user_id, $data ) {
		if ( empty( $data ) ) {
			return true;
		}
		$data = explode( ';', $data );
		$data = array_map( 'trim', $data );
		$data = array_filter( $data );
		$data = array_map(
			function( $item ) {
				return explode( '=', $item );
			},
			$data
		);
		$reader_data = Reader_Data::get_data( $user_id );
		foreach ( $data as $item ) {
			if ( ! isset( $reader_data[ $item[0] ] ) || $reader_data[ $item[0] ] !== $item[1] ) {
				return false;
			}
		}
		return true;
	}
}
Access_Rules::init();
