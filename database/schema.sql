-- WARNING: This schema is for context only and is not meant to be run.
-- Table order and constraints may not be valid for execution.
CREATE TABLE public.allergens (
    id bigint NOT NULL DEFAULT nextval('allergens_id_seq' :: regclass),
    name character varying NOT NULL UNIQUE,
    icon character varying,
    sort_order integer NOT NULL DEFAULT 0,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT allergens_pkey PRIMARY KEY (id)
);

CREATE TABLE public.audit_logs (
    id bigint NOT NULL DEFAULT nextval('audit_logs_id_seq' :: regclass),
    admin_id bigint,
    action_type character varying NOT NULL,
    target_type character varying NOT NULL,
    target_id bigint NOT NULL,
    reason text,
    metadata json,
    ip_address character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT audit_logs_pkey PRIMARY KEY (id),
    CONSTRAINT audit_logs_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES public.users(id)
);

CREATE TABLE public.cache (
    key character varying NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL,
    CONSTRAINT cache_pkey PRIMARY KEY (key)
);

CREATE TABLE public.cache_locks (
    key character varying NOT NULL,
    owner character varying NOT NULL,
    expiration integer NOT NULL,
    CONSTRAINT cache_locks_pkey PRIMARY KEY (key)
);

CREATE TABLE public.customer_activities (
    id bigint NOT NULL DEFAULT nextval('customer_activities_id_seq' :: regclass),
    customer_id bigint NOT NULL,
    event_type character varying NOT NULL,
    title character varying NOT NULL,
    detail text,
    ip_address character varying,
    device character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT customer_activities_pkey PRIMARY KEY (id),
    CONSTRAINT customer_activities_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id)
);

CREATE TABLE public.customers (
    id bigint NOT NULL DEFAULT nextval('customers_id_seq' :: regclass),
    name character varying NOT NULL,
    phone character varying NOT NULL UNIQUE,
    email character varying NOT NULL UNIQUE,
    password character varying NOT NULL,
    email_verified_at timestamp without time zone,
    remember_token character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    customer_public_id character varying NOT NULL UNIQUE,
    account_type character varying NOT NULL DEFAULT 'registered' :: character varying,
    risk_level character varying NOT NULL DEFAULT 'none' :: character varying,
    risk_tooltip character varying,
    orders_count integer NOT NULL DEFAULT 0,
    total_spend numeric NOT NULL DEFAULT '0' :: numeric,
    last_active_at timestamp without time zone,
    loyalty_points integer NOT NULL DEFAULT 0,
    registration_source character varying NOT NULL DEFAULT 'web' :: character varying,
    CONSTRAINT customers_pkey PRIMARY KEY (id)
);

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL DEFAULT nextval('failed_jobs_id_seq' :: regclass),
    uuid character varying NOT NULL UNIQUE,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT failed_jobs_pkey PRIMARY KEY (id)
);

CREATE TABLE public.feature_usage (
    id bigint NOT NULL DEFAULT nextval('feature_usage_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    feature_id bigint NOT NULL,
    usage_value integer NOT NULL DEFAULT 0,
    period_start date NOT NULL,
    period_end date NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT feature_usage_pkey PRIMARY KEY (id),
    CONSTRAINT feature_usage_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT feature_usage_feature_id_foreign FOREIGN KEY (feature_id) REFERENCES public.features(id)
);

CREATE TABLE public.features (
    id bigint NOT NULL DEFAULT nextval('features_id_seq' :: regclass),
    name character varying NOT NULL UNIQUE,
    description text,
    category character varying NOT NULL,
    required_feature_id bigint,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT features_pkey PRIMARY KEY (id),
    CONSTRAINT features_required_feature_id_foreign FOREIGN KEY (required_feature_id) REFERENCES public.features(id)
);

CREATE TABLE public.gdpr_requests (
    id bigint NOT NULL DEFAULT nextval('gdpr_requests_id_seq' :: regclass),
    customer_id bigint NOT NULL,
    type character varying NOT NULL,
    status character varying NOT NULL DEFAULT 'pending' :: character varying,
    reason text,
    handled_by bigint,
    admin_notes text,
    completed_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT gdpr_requests_pkey PRIMARY KEY (id),
    CONSTRAINT gdpr_requests_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id),
    CONSTRAINT gdpr_requests_handled_by_foreign FOREIGN KEY (handled_by) REFERENCES public.users(id)
);

CREATE TABLE public.inventory_items (
    id bigint NOT NULL DEFAULT nextval('inventory_items_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    name character varying NOT NULL,
    category character varying,
    quantity numeric NOT NULL DEFAULT '0' :: numeric,
    unit character varying NOT NULL DEFAULT 'g' :: character varying,
    min_stock numeric NOT NULL DEFAULT '0' :: numeric,
    cost_per_unit numeric NOT NULL DEFAULT '0' :: numeric,
    supplier character varying,
    is_critical boolean NOT NULL DEFAULT false,
    auto_reorder boolean NOT NULL DEFAULT false,
    nutrition json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT inventory_items_pkey PRIMARY KEY (id),
    CONSTRAINT inventory_items_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.inventory_settings (
    id bigint NOT NULL DEFAULT nextval('inventory_settings_id_seq' :: regclass),
    vendor_id bigint NOT NULL UNIQUE,
    low_stock_alerts boolean NOT NULL DEFAULT true,
    auto_reorder_enabled boolean NOT NULL DEFAULT false,
    low_stock_threshold integer NOT NULL DEFAULT 10,
    track_nutrition boolean NOT NULL DEFAULT true,
    link_menu_items boolean NOT NULL DEFAULT true,
    settings json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT inventory_settings_pkey PRIMARY KEY (id),
    CONSTRAINT inventory_settings_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.invoices (
    id bigint NOT NULL DEFAULT nextval('invoices_id_seq' :: regclass),
    subscription_id bigint NOT NULL,
    invoice_number character varying NOT NULL UNIQUE,
    amount numeric NOT NULL,
    currency character varying NOT NULL DEFAULT 'EUR' :: character varying,
    status character varying NOT NULL DEFAULT 'pending' :: character varying,
    billing_period_start date NOT NULL,
    billing_period_end date NOT NULL,
    due_date date NOT NULL,
    paid_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    vat numeric NOT NULL DEFAULT '0' :: numeric,
    pdf_url character varying,
    stripe_invoice_id character varying,
    stripe_hosted_url character varying,
    CONSTRAINT invoices_pkey PRIMARY KEY (id),
    CONSTRAINT invoices_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id)
);

CREATE TABLE public.job_batches (
    id character varying NOT NULL,
    name character varying NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer,
    CONSTRAINT job_batches_pkey PRIMARY KEY (id)
);

CREATE TABLE public.jobs (
    id bigint NOT NULL DEFAULT nextval('jobs_id_seq' :: regclass),
    queue character varying NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL,
    CONSTRAINT jobs_pkey PRIMARY KEY (id)
);

CREATE TABLE public.menu_categories (
    id bigint NOT NULL DEFAULT nextval('menu_categories_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    name character varying NOT NULL,
    slug character varying NOT NULL,
    default_tax_category character varying NOT NULL DEFAULT 'food' :: character varying,
    sort_order integer NOT NULL DEFAULT 0,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    tax_category_id bigint,
    CONSTRAINT menu_categories_pkey PRIMARY KEY (id),
    CONSTRAINT menu_categories_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT menu_categories_tax_category_id_foreign FOREIGN KEY (tax_category_id) REFERENCES public.tax_categories(id)
);

CREATE TABLE public.menu_item_allergens (
    id bigint NOT NULL DEFAULT nextval('menu_item_allergens_id_seq' :: regclass),
    menu_item_id bigint NOT NULL,
    allergen_id bigint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT menu_item_allergens_pkey PRIMARY KEY (id),
    CONSTRAINT menu_item_allergens_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id),
    CONSTRAINT menu_item_allergens_allergen_id_foreign FOREIGN KEY (allergen_id) REFERENCES public.allergens(id)
);

CREATE TABLE public.menu_item_ingredients (
    id bigint NOT NULL DEFAULT nextval('menu_item_ingredients_id_seq' :: regclass),
    menu_item_id bigint NOT NULL,
    inventory_item_id bigint NOT NULL,
    quantity numeric NOT NULL,
    unit character varying NOT NULL DEFAULT 'g' :: character varying,
    is_critical boolean NOT NULL DEFAULT false,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT menu_item_ingredients_pkey PRIMARY KEY (id),
    CONSTRAINT menu_item_ingredients_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id),
    CONSTRAINT menu_item_ingredients_inventory_item_id_foreign FOREIGN KEY (inventory_item_id) REFERENCES public.inventory_items(id)
);

CREATE TABLE public.menu_item_modifier_groups (
    id bigint NOT NULL DEFAULT nextval('menu_item_modifier_groups_id_seq' :: regclass),
    menu_item_id bigint NOT NULL,
    modifier_group_id bigint NOT NULL,
    sort_order integer NOT NULL DEFAULT 0,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT menu_item_modifier_groups_pkey PRIMARY KEY (id),
    CONSTRAINT menu_item_modifier_groups_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id),
    CONSTRAINT menu_item_modifier_groups_modifier_group_id_foreign FOREIGN KEY (modifier_group_id) REFERENCES public.modifier_groups(id)
);

CREATE TABLE public.menu_item_tags (
    id bigint NOT NULL DEFAULT nextval('menu_item_tags_id_seq' :: regclass),
    menu_item_id bigint NOT NULL,
    special_tag_id bigint NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT menu_item_tags_pkey PRIMARY KEY (id),
    CONSTRAINT menu_item_tags_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id),
    CONSTRAINT menu_item_tags_special_tag_id_foreign FOREIGN KEY (special_tag_id) REFERENCES public.special_tags(id)
);

CREATE TABLE public.menu_item_translations (
    id bigint NOT NULL DEFAULT nextval('menu_item_translations_id_seq' :: regclass),
    menu_item_id bigint NOT NULL,
    language character varying NOT NULL,
    name character varying NOT NULL,
    description text,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT menu_item_translations_pkey PRIMARY KEY (id),
    CONSTRAINT menu_item_translations_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id)
);

CREATE TABLE public.menu_items (
    id bigint NOT NULL DEFAULT nextval('menu_items_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    menu_category_id bigint NOT NULL,
    name character varying NOT NULL,
    description text,
    price numeric NOT NULL,
    image_url character varying,
    available boolean NOT NULL DEFAULT true,
    calories integer NOT NULL DEFAULT 0,
    fat numeric NOT NULL DEFAULT '0' :: numeric,
    carbs numeric NOT NULL DEFAULT '0' :: numeric,
    protein numeric NOT NULL DEFAULT '0' :: numeric,
    vat_rate numeric NOT NULL DEFAULT '20' :: numeric,
    tax_category character varying NOT NULL DEFAULT 'food' :: character varying,
    dietary_preference character varying,
    allergies json,
    special_tags json,
    has_discount boolean NOT NULL DEFAULT false,
    discount_percent numeric NOT NULL DEFAULT '0' :: numeric,
    discounted_price numeric,
    paid_addons json,
    free_addons json,
    removable_items json,
    translations json,
    ingredients json,
    rating numeric NOT NULL DEFAULT '0' :: numeric,
    review_count integer NOT NULL DEFAULT 0,
    ordered_count integer NOT NULL DEFAULT 0,
    sort_order integer NOT NULL DEFAULT 0,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    is_active boolean NOT NULL DEFAULT true,
    CONSTRAINT menu_items_pkey PRIMARY KEY (id),
    CONSTRAINT menu_items_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT menu_items_menu_category_id_foreign FOREIGN KEY (menu_category_id) REFERENCES public.menu_categories(id)
);

CREATE TABLE public.migrations (
    id integer NOT NULL DEFAULT nextval('migrations_id_seq' :: regclass),
    migration character varying NOT NULL,
    batch integer NOT NULL,
    CONSTRAINT migrations_pkey PRIMARY KEY (id)
);

CREATE TABLE public.modifier_groups (
    id bigint NOT NULL DEFAULT nextval('modifier_groups_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    name character varying NOT NULL,
    type character varying NOT NULL DEFAULT 'single' :: character varying,
    min_selection integer NOT NULL DEFAULT 0,
    max_selection integer NOT NULL DEFAULT 1,
    is_required boolean NOT NULL DEFAULT false,
    sort_order integer NOT NULL DEFAULT 0,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT modifier_groups_pkey PRIMARY KEY (id),
    CONSTRAINT modifier_groups_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.modifier_options (
    id bigint NOT NULL DEFAULT nextval('modifier_options_id_seq' :: regclass),
    modifier_group_id bigint NOT NULL,
    name character varying NOT NULL,
    price_adjustment numeric NOT NULL DEFAULT '0' :: numeric,
    sort_order integer NOT NULL DEFAULT 0,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT modifier_options_pkey PRIMARY KEY (id),
    CONSTRAINT modifier_options_modifier_group_id_foreign FOREIGN KEY (modifier_group_id) REFERENCES public.modifier_groups(id)
);

CREATE TABLE public.order_items (
    id bigint NOT NULL DEFAULT nextval('order_items_id_seq' :: regclass),
    menu_item_id bigint,
    name character varying NOT NULL,
    price numeric NOT NULL,
    quantity integer NOT NULL DEFAULT 1,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT order_items_pkey PRIMARY KEY (id),
    CONSTRAINT order_items_menu_item_id_foreign FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id)
);

CREATE TABLE public.orders (
    id bigint NOT NULL DEFAULT nextval('orders_id_seq' :: regclass),
    order_public_id character varying NOT NULL UNIQUE,
    customer_id bigint NOT NULL,
    vendor_id bigint NOT NULL,
    status character varying NOT NULL DEFAULT 'pending' :: character varying,
    items_count integer NOT NULL DEFAULT 0,
    amount numeric NOT NULL,
    currency character varying NOT NULL DEFAULT 'EUR' :: character varying,
    payment_method character varying,
    transaction_id character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    items json,
    payment_pending boolean NOT NULL DEFAULT false,
    payment_received boolean NOT NULL DEFAULT false,
    payment_confirmed_at timestamp without time zone,
    payment_note text,
    ready_at timestamp without time zone,
    picked_up_at timestamp without time zone,
    order_number bigint,
    order_type character varying NOT NULL DEFAULT 'dine-in' :: character varying,
    table_number character varying,
    service_fee numeric NOT NULL DEFAULT '0' :: numeric,
    vat_amount numeric NOT NULL DEFAULT '0' :: numeric,
    course character varying,
    guest_count integer NOT NULL DEFAULT 1,
    table_session_id bigint,
    waiter_confirmed boolean NOT NULL DEFAULT false,
    waiter_confirmed_at timestamp without time zone,
    served_at timestamp without time zone,
    cancelled_at timestamp without time zone,
    cancelled_reason character varying,
    CONSTRAINT orders_pkey PRIMARY KEY (id),
    CONSTRAINT orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id),
    CONSTRAINT orders_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT orders_table_session_id_foreign FOREIGN KEY (table_session_id) REFERENCES public.table_sessions(id)
);

CREATE TABLE public.password_reset_tokens (
    email character varying NOT NULL,
    token character varying NOT NULL,
    created_at timestamp without time zone,
    CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)
);

CREATE TABLE public.payment_methods (
    id bigint NOT NULL DEFAULT nextval('payment_methods_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    card_brand character varying,
    last4 character varying,
    exp_month character varying,
    exp_year character varying,
    stripe_payment_method_id character varying,
    is_default boolean NOT NULL DEFAULT false,
    billing_email character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT payment_methods_pkey PRIMARY KEY (id),
    CONSTRAINT payment_methods_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.payments (
    id bigint NOT NULL DEFAULT nextval('payments_id_seq' :: regclass),
    invoice_id bigint NOT NULL,
    payment_provider character varying NOT NULL DEFAULT 'stripe' :: character varying,
    provider_transaction_id character varying,
    amount numeric NOT NULL,
    currency character varying NOT NULL DEFAULT 'EUR' :: character varying,
    status character varying NOT NULL DEFAULT 'pending' :: character varying,
    payment_method character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT payments_pkey PRIMARY KEY (id),
    CONSTRAINT payments_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES public.invoices(id)
);

CREATE TABLE public.permissions (
    id bigint NOT NULL DEFAULT nextval('permissions_id_seq' :: regclass),
    name character varying NOT NULL UNIQUE,
    label character varying NOT NULL,
    group character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT permissions_pkey PRIMARY KEY (id)
);

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL DEFAULT nextval('personal_access_tokens_id_seq' :: regclass),
    tokenable_type character varying NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying NOT NULL UNIQUE,
    abilities text,
    last_used_at timestamp without time zone,
    expires_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id)
);

CREATE TABLE public.plan_features (
    id bigint NOT NULL DEFAULT nextval('plan_features_id_seq' :: regclass),
    plan_id bigint NOT NULL,
    feature_id bigint NOT NULL,
    is_inherited boolean NOT NULL DEFAULT false,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT plan_features_pkey PRIMARY KEY (id),
    CONSTRAINT plan_features_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.subscription_plans(id),
    CONSTRAINT plan_features_feature_id_foreign FOREIGN KEY (feature_id) REFERENCES public.features(id)
);

CREATE TABLE public.refunds (
    id bigint NOT NULL DEFAULT nextval('refunds_id_seq' :: regclass),
    refund_public_id character varying NOT NULL UNIQUE,
    customer_id bigint NOT NULL,
    order_id bigint NOT NULL,
    type character varying NOT NULL,
    status character varying NOT NULL DEFAULT 'open' :: character varying,
    amount numeric NOT NULL,
    currency character varying NOT NULL DEFAULT 'EUR' :: character varying,
    reason character varying NOT NULL,
    description text,
    handled_by bigint,
    admin_notes text,
    resolved_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT refunds_pkey PRIMARY KEY (id),
    CONSTRAINT refunds_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id),
    CONSTRAINT refunds_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id),
    CONSTRAINT refunds_handled_by_foreign FOREIGN KEY (handled_by) REFERENCES public.users(id)
);

CREATE TABLE public.reservations (
    id bigint NOT NULL DEFAULT nextval('reservations_id_seq' :: regclass),
    reservation_public_id character varying NOT NULL UNIQUE,
    vendor_id bigint NOT NULL,
    customer_id bigint,
    guest_name character varying,
    guest_email character varying,
    guest_phone character varying,
    date date NOT NULL,
    time time without time zone NOT NULL,
    party_size integer NOT NULL DEFAULT 2,
    status character varying NOT NULL DEFAULT 'pending' :: character varying,
    customer_note text,
    vendor_note text,
    table_number character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT reservations_pkey PRIMARY KEY (id),
    CONSTRAINT reservations_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT reservations_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id)
);

CREATE TABLE public.restaurant_tables (
    id bigint NOT NULL DEFAULT nextval('restaurant_tables_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    number integer NOT NULL,
    name character varying NOT NULL,
    qr_token character varying NOT NULL UNIQUE,
    is_active boolean NOT NULL DEFAULT true,
    qr_created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_scanned_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT restaurant_tables_pkey PRIMARY KEY (id),
    CONSTRAINT restaurant_tables_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.reviews (
    id bigint NOT NULL DEFAULT nextval('reviews_id_seq' :: regclass),
    review_public_id character varying NOT NULL UNIQUE,
    customer_id bigint NOT NULL,
    vendor_id bigint NOT NULL,
    order_id bigint,
    rating smallint NOT NULL,
    text text,
    flagged boolean NOT NULL DEFAULT false,
    flag_reason character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    vendor_reply text,
    vendor_replied_at timestamp without time zone,
    CONSTRAINT reviews_pkey PRIMARY KEY (id),
    CONSTRAINT reviews_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id),
    CONSTRAINT reviews_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT reviews_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id)
);

CREATE TABLE public.role_permission (
    id bigint NOT NULL DEFAULT nextval('role_permission_id_seq' :: regclass),
    role_id bigint NOT NULL,
    permission_id bigint NOT NULL,
    CONSTRAINT role_permission_pkey PRIMARY KEY (id),
    CONSTRAINT role_permission_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id),
    CONSTRAINT role_permission_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id)
);

CREATE TABLE public.roles (
    id bigint NOT NULL DEFAULT nextval('roles_id_seq' :: regclass),
    name character varying NOT NULL UNIQUE,
    label character varying NOT NULL,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT roles_pkey PRIMARY KEY (id)
);

CREATE TABLE public.sessions (
    id character varying NOT NULL,
    user_id bigint,
    ip_address character varying,
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL,
    CONSTRAINT sessions_pkey PRIMARY KEY (id)
);

CREATE TABLE public.special_tags (
    id bigint NOT NULL DEFAULT nextval('special_tags_id_seq' :: regclass),
    slug character varying NOT NULL UNIQUE,
    label character varying NOT NULL,
    icon character varying,
    sort_order integer NOT NULL DEFAULT 0,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT special_tags_pkey PRIMARY KEY (id)
);

CREATE TABLE public.subscription_events (
    id bigint NOT NULL DEFAULT nextval('subscription_events_id_seq' :: regclass),
    subscription_id bigint NOT NULL,
    event_type character varying NOT NULL,
    previous_plan_id bigint,
    new_plan_id bigint,
    metadata json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT subscription_events_pkey PRIMARY KEY (id),
    CONSTRAINT subscription_events_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id),
    CONSTRAINT subscription_events_previous_plan_id_foreign FOREIGN KEY (previous_plan_id) REFERENCES public.subscription_plans(id),
    CONSTRAINT subscription_events_new_plan_id_foreign FOREIGN KEY (new_plan_id) REFERENCES public.subscription_plans(id)
);

CREATE TABLE public.subscription_plans (
    id bigint NOT NULL DEFAULT nextval('subscription_plans_id_seq' :: regclass),
    name character varying NOT NULL,
    monthly_price numeric NOT NULL,
    yearly_price numeric NOT NULL,
    max_users integer NOT NULL,
    parent_plan_id bigint,
    is_popular boolean NOT NULL DEFAULT false,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    description text,
    currency character varying NOT NULL DEFAULT 'EUR' :: character varying,
    is_active boolean NOT NULL DEFAULT true,
    CONSTRAINT subscription_plans_pkey PRIMARY KEY (id),
    CONSTRAINT subscription_plans_parent_plan_id_foreign FOREIGN KEY (parent_plan_id) REFERENCES public.subscription_plans(id)
);

CREATE TABLE public.subscriptions (
    id bigint NOT NULL DEFAULT nextval('subscriptions_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    plan_id bigint NOT NULL,
    status character varying NOT NULL DEFAULT 'active' :: character varying,
    billing_cycle character varying NOT NULL DEFAULT 'monthly' :: character varying,
    start_date date NOT NULL,
    next_billing_date date NOT NULL,
    auto_renew boolean NOT NULL DEFAULT true,
    stripe_subscription_id character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    stripe_customer_id character varying,
    cancelled_at timestamp without time zone,
    paused_at timestamp without time zone,
    CONSTRAINT subscriptions_pkey PRIMARY KEY (id),
    CONSTRAINT subscriptions_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT subscriptions_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.subscription_plans(id)
);

CREATE TABLE public.table_sessions (
    id bigint NOT NULL DEFAULT nextval('table_sessions_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    table_number integer,
    table_name character varying,
    status character varying NOT NULL DEFAULT 'active' :: character varying,
    batch_started_at timestamp without time zone,
    batch_window_seconds integer NOT NULL DEFAULT 90,
    batch_released_at timestamp without time zone,
    current_course character varying,
    closed_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT table_sessions_pkey PRIMARY KEY (id),
    CONSTRAINT table_sessions_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.tax_categories (
    id bigint NOT NULL DEFAULT nextval('tax_categories_id_seq' :: regclass),
    country character varying NOT NULL,
    slug character varying NOT NULL,
    name character varying NOT NULL,
    vat_rate numeric NOT NULL,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT tax_categories_pkey PRIMARY KEY (id)
);

CREATE TABLE public.team_members (
    id bigint NOT NULL DEFAULT nextval('team_members_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    name character varying NOT NULL,
    email character varying NOT NULL UNIQUE,
    password character varying,
    role character varying NOT NULL DEFAULT 'waiter' :: character varying,
    permissions json,
    status character varying NOT NULL DEFAULT 'invited' :: character varying,
    invitation_token character varying UNIQUE,
    invited_at timestamp without time zone,
    joined_at timestamp without time zone,
    remember_token character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT team_members_pkey PRIMARY KEY (id),
    CONSTRAINT team_members_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.users (
    id bigint NOT NULL DEFAULT nextval('users_id_seq' :: regclass),
    name character varying NOT NULL,
    email character varying NOT NULL UNIQUE,
    email_verified_at timestamp without time zone,
    password character varying NOT NULL,
    remember_token character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp without time zone,
    role_id bigint,
    CONSTRAINT users_pkey PRIMARY KEY (id),
    CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id)
);

CREATE TABLE public.vendor_activities (
    id bigint NOT NULL DEFAULT nextval('vendor_activities_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    event_type character varying NOT NULL,
    title character varying NOT NULL,
    description text,
    color character varying NOT NULL DEFAULT 'bg-gray-600' :: character varying,
    actor character varying NOT NULL DEFAULT 'System' :: character varying,
    metadata json,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT vendor_activities_pkey PRIMARY KEY (id),
    CONSTRAINT vendor_activities_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.vendor_request_changes (
    id bigint NOT NULL DEFAULT nextval('vendor_request_changes_id_seq' :: regclass),
    vendor_id bigint NOT NULL,
    restaurant_name character varying,
    legal_entity_name character varying,
    business_registration_number character varying,
    vat_number character varying,
    country character varying,
    city character varying,
    address character varying,
    admin_notes text,
    status boolean NOT NULL DEFAULT false,
    checked_by bigint,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    vendor_notes text,
    CONSTRAINT vendor_request_changes_pkey PRIMARY KEY (id),
    CONSTRAINT vendor_request_changes_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id),
    CONSTRAINT vendor_request_changes_checked_by_foreign FOREIGN KEY (checked_by) REFERENCES public.users(id)
);

CREATE TABLE public.vendor_settings (
    id bigint NOT NULL DEFAULT nextval('vendor_settings_id_seq' :: regclass),
    vendor_id bigint NOT NULL UNIQUE,
    description character varying,
    logo_url character varying,
    cover_photo_url character varying,
    is_live_and_discoverable boolean NOT NULL DEFAULT false,
    business_hours json,
    accept_on_site boolean NOT NULL DEFAULT true,
    stripe_enabled boolean NOT NULL DEFAULT false,
    currency character varying NOT NULL DEFAULT 'EUR' :: character varying,
    service_fee_rate numeric NOT NULL DEFAULT '0' :: numeric,
    invoice_prefix character varying NOT NULL DEFAULT 'INV' :: character varying,
    next_invoice_number integer NOT NULL DEFAULT 1001,
    auto_generate_receipts boolean NOT NULL DEFAULT true,
    company_type character varying,
    first_invoice_issued boolean NOT NULL DEFAULT false,
    number_of_tables integer NOT NULL DEFAULT 10,
    table_prefix character varying NOT NULL DEFAULT 'T' :: character varying,
    enable_shared_basket boolean NOT NULL DEFAULT true,
    max_guests_per_table integer NOT NULL DEFAULT 10,
    enable_reservations boolean NOT NULL DEFAULT false,
    total_tables_for_reservations integer NOT NULL DEFAULT 0,
    max_table_capacity integer NOT NULL DEFAULT 6,
    auto_accept_orders boolean NOT NULL DEFAULT false,
    estimated_prep_time integer NOT NULL DEFAULT 20,
    max_orders_per_slot integer NOT NULL DEFAULT 50,
    allow_guest_ordering boolean NOT NULL DEFAULT true,
    require_phone_number boolean NOT NULL DEFAULT false,
    min_order_amount numeric NOT NULL DEFAULT '0' :: numeric,
    max_order_amount numeric NOT NULL DEFAULT '1000' :: numeric,
    inventory_tracking_enabled boolean NOT NULL DEFAULT false,
    auto_stock_deduction boolean NOT NULL DEFAULT false,
    allow_negative_stock boolean NOT NULL DEFAULT false,
    auto_mark_unavailable_critical boolean NOT NULL DEFAULT true,
    notify_email_new_order boolean NOT NULL DEFAULT true,
    notify_email_review boolean NOT NULL DEFAULT true,
    notify_sms_new_order boolean NOT NULL DEFAULT false,
    notify_push_new_order boolean NOT NULL DEFAULT true,
    notification_email character varying,
    enable_reviews boolean NOT NULL DEFAULT true,
    enable_menu_reviews boolean NOT NULL DEFAULT false,
    allow_anonymous_reviews boolean NOT NULL DEFAULT false,
    default_language character varying NOT NULL DEFAULT 'en' :: character varying,
    supported_languages json,
    loyalty_enabled boolean NOT NULL DEFAULT false,
    points_per_euro integer NOT NULL DEFAULT 10,
    minimum_redemption_points integer NOT NULL DEFAULT 100,
    point_value numeric NOT NULL DEFAULT 0.01,
    points_expiry_days integer NOT NULL DEFAULT 365,
    menu_theme character varying NOT NULL DEFAULT 'classic' :: character varying,
    primary_color character varying NOT NULL DEFAULT '#1a1a1a' :: character varying,
    accent_color character varying NOT NULL DEFAULT '#f59e0b' :: character varying,
    menu_layout character varying NOT NULL DEFAULT 'grid' :: character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    stripe_account_id character varying,
    stripe_onboarding_complete boolean NOT NULL DEFAULT false,
    redemption_rate numeric NOT NULL DEFAULT 0.05,
    notify_push_order_ready boolean NOT NULL DEFAULT true,
    date_format character varying NOT NULL DEFAULT 'DD.MM.YYYY' :: character varying,
    time_format character varying NOT NULL DEFAULT '24h' :: character varying,
    background_image_url character varying,
    show_in_top_customers boolean NOT NULL DEFAULT true,
    data_retention_days integer NOT NULL DEFAULT 365,
    CONSTRAINT vendor_settings_pkey PRIMARY KEY (id),
    CONSTRAINT vendor_settings_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.vendor_takeaway_qrs (
    id bigint NOT NULL DEFAULT nextval('vendor_takeaway_qrs_id_seq' :: regclass),
    vendor_id bigint NOT NULL UNIQUE,
    qr_token character varying NOT NULL UNIQUE,
    last_regenerated_at timestamp without time zone,
    last_scanned_at timestamp without time zone,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT vendor_takeaway_qrs_pkey PRIMARY KEY (id),
    CONSTRAINT vendor_takeaway_qrs_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES public.vendors(id)
);

CREATE TABLE public.vendors (
    id bigint NOT NULL DEFAULT nextval('vendors_id_seq' :: regclass),
    name character varying NOT NULL,
    country character varying NOT NULL,
    phone character varying NOT NULL UNIQUE,
    email character varying NOT NULL UNIQUE,
    password character varying NOT NULL,
    email_verified_at timestamp without time zone,
    remember_token character varying,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    vendor_public_id character varying NOT NULL UNIQUE,
    restaurant_name character varying,
    legal_entity_name character varying,
    business_registration_number character varying,
    vat_number character varying,
    website character varying,
    city character varying,
    address character varying,
    status character varying NOT NULL DEFAULT 'pending' :: character varying,
    live_status character varying NOT NULL DEFAULT 'not-live' :: character varying,
    risk_level character varying NOT NULL DEFAULT 'none' :: character varying,
    last_activity_at timestamp without time zone,
    slug character varying UNIQUE,
    orders_count integer NOT NULL DEFAULT 0,
    revenue_total numeric NOT NULL DEFAULT '0' :: numeric,
    users_used integer NOT NULL DEFAULT 0,
    payment_status character varying NOT NULL DEFAULT 'paid' :: character varying,
    payment_last_success timestamp without time zone,
    payment_failures_24h integer NOT NULL DEFAULT 0,
    CONSTRAINT vendors_pkey PRIMARY KEY (id)
);
