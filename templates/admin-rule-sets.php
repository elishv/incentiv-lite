<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included from a class method; its variables are local to that method, not globals.
if (!defined('ABSPATH')) {
    exit;
}

$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.
$rule_set_id = isset($_GET['rule_set_id']) ? sanitize_text_field(wp_unslash($_GET['rule_set_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.

if ($action === 'edit' || $action === 'new') {
    // The edit template includes the wizard overlay markup itself; don't include it here too.
    include INCV_PLUGIN_DIR . 'templates/admin-rule-set-edit.php';
} else {
    // List view
    $rule_sets = INCV_Discount_Rules::get_all_rule_sets();
    $usage_counts = get_option('incv_rule_usage_counts', array());
    $now_ts = current_time('timestamp');

    // Compute status per rule + counters
    $statuses = array();
    $count_active = 0;
    $count_disabled = 0;
    $count_scheduled = 0;
    $count_draft = 0;
    $max_usage = 1;

    foreach ($rule_sets as $rs) {
        $id = $rs['id'];
        $enabled = isset($rs['enabled']) && $rs['enabled'] === 'yes';
        $start = !empty($rs['schedule_start']) ? strtotime($rs['schedule_start']) : 0;
        $end = !empty($rs['schedule_end']) ? strtotime($rs['schedule_end']) : 0;

        if (isset($rs['status']) && $rs['status'] === 'draft') {
            $statuses[$id] = 'draft';
            $count_draft++;
        } elseif (!$enabled) {
            $statuses[$id] = 'disabled';
            $count_disabled++;
        } elseif ($start && $start > $now_ts) {
            $statuses[$id] = 'scheduled';
            $count_scheduled++;
        } elseif ($end && $end < $now_ts) {
            $statuses[$id] = 'disabled';
            $count_disabled++;
        } else {
            $statuses[$id] = 'active';
            $count_active++;
        }

        $usage = isset($usage_counts[$id . '_total']) ? intval($usage_counts[$id . '_total']) : 0;
        $max_usage = max($max_usage, $usage);
    }

    $method_labels = array(
        'all' => array(__( 'All products', 'incentiv' ), 'select_all'),
        'category' => array(__( 'By categories', 'incentiv' ), 'category'),
        'product_ids' => array(__( 'Selected products', 'incentiv' ), 'inventory_2'),
        'attribute' => array(__( 'By attribute', 'incentiv' ), 'tune'),
    );

    $status_pills = array(
        'active' => ( '<span class="incv-pill incv-pill-success"><span class="incv-pill-dot"></span>' . esc_html__( 'Active', 'incentiv' ) . '</span>' ),
        'scheduled' => ( '<span class="incv-pill incv-pill-warning"><span class="incv-pill-dot"></span>' . esc_html__( 'Scheduled', 'incentiv' ) . '</span>' ),
        'disabled' => ( '<span class="incv-pill incv-pill-muted"><span class="incv-pill-dot"></span>' . esc_html__( 'Disabled', 'incentiv' ) . '</span>' ),
        'draft' => ( '<span class="incv-pill incv-pill-draft"><span class="incv-pill-dot"></span>' . esc_html__( 'Draft', 'incentiv' ) . '</span>' ),
    );

    $grid_cols = 'grid-template-columns:2.4fr 1fr 1.3fr 1fr .9fr 1.2fr;';
    ?>
    <div class="wrap incv-admin-wrap">
        <?php include INCV_PLUGIN_DIR . 'templates/admin-nav-tabs.php'; ?>

        <div class="incv-page-head">
            <div>
                <h2 class="incv-page-title"><?php esc_html_e( 'Discount rules', 'incentiv' ); ?></h2>
                <div class="incv-page-sub"><?php echo esc_html(count($rule_sets)); ?> <?php echo wp_kses_post( __( 'Rules &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_active); ?> <?php esc_html_e( 'active', 'incentiv' ); ?></div>
            </div>
            <div class="incv-head-actions">
                <button type="button" class="incv-btn incv-btn-soft-purple" id="incv-quick-create-open">
                    <span class="msr">bolt</span><?php esc_html_e( 'Quick create', 'incentiv' ); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=incv-discount-rules&action=new')); ?>" class="incv-btn incv-btn-outline">
                    <span class="msr">auto_awesome</span><?php esc_html_e( 'Wizard', 'incentiv' ); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=incv-discount-rules&action=new&skip_wizard=1')); ?>" class="incv-btn incv-btn-primary">
                    <span class="msr">add</span><?php esc_html_e( 'New rule', 'incentiv' ); ?>
                </a>
            </div>
        </div>

        <?php if (empty($rule_sets)): ?>
            <div class="incv-empty-state">
                <p><?php esc_html_e( 'No discount rules found. Create your first rule!', 'incentiv' ); ?></p>
                <p style="display:flex;gap:10px;justify-content:center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=incv-discount-rules&action=new')); ?>" class="incv-btn incv-btn-primary">
                        <span class="msr">auto_awesome</span><?php esc_html_e( 'Create a rule with the wizard', 'incentiv' ); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=incv-discount-rules&action=new&skip_wizard=1')); ?>" class="incv-btn incv-btn-outline">
                        <?php esc_html_e( 'Create a rule manually', 'incentiv' ); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>

            <!-- Filter chips -->
            <div class="incv-filter-chips">
                <span class="incv-chip incv-chip-active" data-filter="all"><?php echo wp_kses_post( __( 'All &middot;', 'incentiv' ) ); ?> <?php echo esc_html(count($rule_sets)); ?></span>
                <span class="incv-chip" data-filter="active"><?php echo wp_kses_post( __( 'Active &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_active); ?></span>
                <span class="incv-chip" data-filter="disabled"><?php echo wp_kses_post( __( 'Disabled &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_disabled); ?></span>
                <span class="incv-chip" data-filter="scheduled"><?php echo wp_kses_post( __( 'Scheduled &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_scheduled); ?></span>
                <?php if ($count_draft): ?>
                <span class="incv-chip" data-filter="draft"><?php echo wp_kses_post( __( 'Drafts &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_draft); ?></span>
                <?php endif; ?>
            </div>

            <!-- Rules table -->
            <div class="incv-tablecard">
                <div class="incv-tablecard-head" style="<?php echo esc_attr($grid_cols); ?>">
                    <span><?php esc_html_e( 'Rule name', 'incentiv' ); ?></span><span><?php esc_html_e( 'Status', 'incentiv' ); ?></span><span><?php esc_html_e( 'Selection method', 'incentiv' ); ?></span><span><?php esc_html_e( 'Tiers', 'incentiv' ); ?></span><span><?php esc_html_e( 'Performance', 'incentiv' ); ?></span><span style="text-align:left;"><?php esc_html_e( 'Actions', 'incentiv' ); ?></span>
                </div>

                <?php foreach ($rule_sets as $rule_set):
                    $id = $rule_set['id'];
                    $status = $statuses[$id];
                    $method = isset($rule_set['selection_method']) ? $rule_set['selection_method'] : 'all';
                    $method_info = isset($method_labels[$method]) ? $method_labels[$method] : array(__( 'Unknown', 'incentiv' ), 'help');
                    $usage = isset($usage_counts[$id . '_total']) ? intval($usage_counts[$id . '_total']) : 0;
                    $usage_ratio = $max_usage > 0 ? $usage / $max_usage : 0;
                    $is_disabled = ($status === 'disabled');
                    $has_gift = !empty($rule_set['discount_rules']) && array_filter($rule_set['discount_rules'], function ($r) {
                        return isset($r['type']) && $r['type'] === 'percentage' && floatval($r['discount']) >= 100;
                    });

                    $icon = $has_gift ? 'redeem' : $method_info[1];
                    $icon_cls = $is_disabled ? 'incv-row-icon-muted' : ($has_gift ? 'incv-row-icon-purple' : '');
                    $chip_cls = $is_disabled ? 'incv-tier-chip-muted' : ($status === 'scheduled' ? 'incv-tier-chip-orange' : '');

                    // Schedule meta line
                    $meta_line = $id;
                    if ($status === 'scheduled' && !empty($rule_set['schedule_start'])) {
                        $meta_line = ( __( 'Scheduled ·', 'incentiv' ) . ' ' ) . date_i18n(__( 'j M', 'incentiv' ), strtotime($rule_set['schedule_start']));
                    }
                ?>
                <div class="incv-tablecard-row <?php echo $is_disabled ? 'incv-row-disabled' : ''; ?>"
                     style="<?php echo esc_attr($grid_cols); ?>" data-incv-status="<?php echo esc_attr($status); ?>">

                    <div style="display:flex;align-items:center;gap:11px;min-width:0;">
                        <span class="incv-row-icon <?php echo esc_attr($icon_cls); ?>"><span class="msr"><?php echo esc_html($icon); ?></span></span>
                        <div style="min-width:0;">
                            <div class="incv-row-title"><?php echo esc_html($rule_set['name']); ?></div>
                            <div class="incv-row-meta"><?php echo esc_html($meta_line); ?></div>
                        </div>
                    </div>

                    <div><?php echo $status_pills[$status]; // phpcs:ignore ?></div>

                    <div class="incv-row-method">
                        <span class="msr"><?php echo esc_html($method_info[1]); ?></span><?php echo esc_html($method_info[0]); ?>
                    </div>

                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php
                        $tiers = isset($rule_set['discount_rules']) ? $rule_set['discount_rules'] : array();
                        $shown = 0;
                        foreach ($tiers as $tier) {
                            if ($shown >= 3) {
                                echo '<span class="incv-tier-chip ' . esc_attr($chip_cls) . '">+' . esc_html(count($tiers) - 3) . '</span>';
                                break;
                            }
                            $is_free = isset($tier['type']) && $tier['type'] === 'percentage' && floatval($tier['discount']) >= 100;
                            echo '<span class="incv-tier-chip ' . esc_attr($chip_cls ? $chip_cls : ($is_free ? 'incv-tier-chip-purple' : '')) . '">' . esc_html(intval($tier['quantity'])) . '+' . ($is_free ? ' 🎁' : '') . '</span>';
                            $shown++;
                        }
                        if (empty($tiers)) {
                            echo '<span class="incv-tier-chip incv-tier-chip-muted">—</span>';
                        }
                        ?>
                    </div>

                    <div class="incv-usagebars" title="<?php echo esc_attr($usage . ( ' ' . esc_html__( 'Uses', 'incentiv' ) )); ?>">
                        <?php
                        // 6 mini bars ramping up to the rule's relative usage
                        for ($b = 1; $b <= 6; $b++) {
                            $h = max(15, round($usage_ratio * 100 * $b / 6));
                            $hi = (!$is_disabled && $usage > 0 && $b >= 5) ? ' class="hi"' : '';
                            echo '<span' . $hi . ' style="height:' . esc_attr($h) . '%;"></span>'; // phpcs:ignore
                        }
                        ?>
                    </div>

                    <?php $rule_on = isset($rule_set['enabled']) && $rule_set['enabled'] === 'yes'; ?>
                    <div class="incv-row-actions">
                        <button type="button" class="incv-icon-btn incv-toggle-rule <?php echo $rule_on ? 'incv-icon-btn-active' : ''; ?>"
                                title="<?php echo $rule_on ? esc_html__( 'Pause', 'incentiv' ) : esc_html__( 'Activate', 'incentiv' ); ?>"
                                data-rule-id="<?php echo esc_attr($id); ?>"
                                data-enabled="<?php echo $rule_on ? 'yes' : 'no'; ?>">
                            <span class="msr"><?php echo $rule_on ? 'pause' : 'play_arrow'; ?></span>
                        </button>
                        <a class="incv-icon-btn" title="<?php esc_attr_e( 'Edit', 'incentiv' ); ?>"
                           href="<?php echo esc_url(admin_url('admin.php?page=incv-discount-rules&action=edit&rule_set_id=' . urlencode($id))); ?>">
                            <span class="msr">edit</span>
                        </a>
                        <a class="incv-icon-btn" title="<?php esc_attr_e( 'Duplicate', 'incentiv' ); ?>"
                           href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=incv-discount-rules&action=duplicate&rule_set_id=' . urlencode($id)), 'incv_duplicate_rule')); ?>">
                            <span class="msr">content_copy</span>
                        </a>
                        <a class="incv-icon-btn incv-icon-btn-danger" title="<?php esc_attr_e( 'Delete', 'incentiv' ); ?>"
                           href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=incv-discount-rules&action=delete&rule_set_id=' . urlencode($id)), 'incv_delete_rule')); ?>"
                           data-incv-confirm="<?php echo esc_attr_x( 'Are you sure you want to delete this rule?', 'list', 'incentiv' ); ?>">
                            <span class="msr">delete</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Example rules gallery -->
        <?php include INCV_PLUGIN_DIR . 'templates/admin-example-rules.php'; ?>

        <div class="incv-tips-box">
            <h3><span class="msr" style="color:var(--incv-primary);">lightbulb</span> <?php esc_html_e( 'Tips', 'incentiv' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'Each discount rule works independently. You can set different rules for different products or categories', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'If a product meets the conditions of several rules, the first rule found applies', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'Use the duplicate option to create similar rules quickly', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'You can combine included categories with excluding specific products from those categories', 'incentiv' ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>New:</strong> the dashboard shows the real performance of every rule. Track sales in real time', 'incentiv' ) ); ?></li>
            </ul>
        </div>

        <!-- ================= Quick create ================= -->
        <?php $quick_presets = INCV_Admin::get_quick_presets(); ?>
        <div id="incv-quick-create" class="incv-quick-overlay" style="display:none;">
            <div class="incv-quick-backdrop"></div>
            <div class="incv-quick-dialog" role="dialog" aria-modal="true" aria-labelledby="incv-quick-title">
                <div class="incv-quick-head">
                    <span class="msr">bolt</span>
                    <div>
                        <h2 id="incv-quick-title"><?php esc_html_e( 'Quick create', 'incentiv' ); ?></h2>
                        <div class="incv-quick-sub"><?php esc_html_e( 'Choose a template, fill in a number or two, and the rule is live.', 'incentiv' ); ?></div>
                    </div>
                    <button type="button" class="incv-icon-btn" id="incv-quick-close" title="<?php esc_attr_e( 'Close', 'incentiv' ); ?>"><span class="msr">close</span></button>
                </div>

                <div class="incv-quick-body">
                    <div class="incv-quick-presets">
                        <?php $first = true; foreach ($quick_presets as $key => $preset): ?>
                            <button type="button" class="incv-quick-card<?php echo $first ? ' is-active' : ''; ?>" data-preset="<?php echo esc_attr($key); ?>">
                                <span class="msr"><?php echo esc_html($preset['icon']); ?></span>
                                <strong><?php echo esc_html($preset['title']); ?></strong>
                                <span><?php echo esc_html($preset['desc']); ?></span>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>

                    <?php foreach ($quick_presets as $key => $preset): ?>
                        <div class="incv-quick-fields" data-preset="<?php echo esc_attr($key); ?>" style="<?php echo $key === array_key_first($quick_presets) ? '' : 'display:none;'; ?>">
                            <div class="incv-quick-field">
                                <label for="incv-quick-name-<?php echo esc_attr($key); ?>"><?php esc_html_e( 'Rule name', 'incentiv' ); ?></label>
                                <input type="text" id="incv-quick-name-<?php echo esc_attr($key); ?>" class="incv-quick-name"
                                       placeholder="<?php echo esc_attr($preset['title']); ?>">
                            </div>
                            <?php foreach ($preset['fields'] as $fkey => $field): ?>
                                <div class="incv-quick-field">
                                    <label for="incv-quick-<?php echo esc_attr($key . '-' . $fkey); ?>"><?php echo esc_html($field['label']); ?></label>
                                    <div class="incv-quick-input">
                                        <input type="number" id="incv-quick-<?php echo esc_attr($key . '-' . $fkey); ?>"
                                               data-field="<?php echo esc_attr($fkey); ?>"
                                               value="<?php echo esc_attr($field['default']); ?>"
                                               <?php echo isset($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : ''; ?>
                                               <?php echo isset($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : ''; ?>>
                                        <?php if (!empty($field['suffix'])): ?>
                                            <span class="incv-quick-suffix"><?php echo esc_html($field['suffix']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="incv-card-tip" style="margin:4px 0 0;">
                        <?php esc_html_e( 'The rule is created for all products and is active immediately. You can narrow it to categories, add conditions or change the discount split when editing.', 'incentiv' ); ?>
                    </div>
                </div>

                <div class="incv-quick-foot">
                    <span id="incv-quick-status" class="incv-draft-status"></span>
                    <button type="button" class="incv-btn incv-btn-ghost" id="incv-quick-cancel"><?php esc_html_e( 'Cancel', 'incentiv' ); ?></button>
                    <button type="button" class="incv-btn incv-btn-green" id="incv-quick-submit"><span class="msr">check</span><?php esc_html_e( 'Create rule', 'incentiv' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <?php $incv_inline_js = INCV_Helpers::capture( function () { ?>
    jQuery(function ($) {
        var $overlay = $('#incv-quick-create');

        function activePreset() { return $('.incv-quick-card.is-active').data('preset'); }

        $(document).on('click', '#incv-quick-create-open', function () { $overlay.fadeIn(150); });
        $(document).on('click', '#incv-quick-close, #incv-quick-cancel, .incv-quick-backdrop', function () { $overlay.fadeOut(150); });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) { $overlay.fadeOut(150); }
        });

        $(document).on('click', '.incv-quick-card', function () {
            var preset = $(this).data('preset');
            $('.incv-quick-card').removeClass('is-active');
            $(this).addClass('is-active');
            $('.incv-quick-fields').hide().filter('[data-preset="' + preset + '"]').show();
        });

        $(document).on('click', '#incv-quick-submit', function () {
            var $btn = $(this).prop('disabled', true);
            var preset = activePreset();
            var $fields = $('.incv-quick-fields[data-preset="' + preset + '"]');

            var data = {
                action: 'incv_quick_create_rule',
                nonce: (window.incvUi && incvUi.nonce) || '',
                preset: preset,
                name: $fields.find('.incv-quick-name').val() || ''
            };
            $fields.find('input[data-field]').each(function () {
                data[$(this).data('field')] = $(this).val();
            });

            $('#incv-quick-status').text(<?php echo wp_json_encode( __( 'Creating…', 'incentiv' ) ); ?>).addClass('is-visible');

            $.post((window.incvUi && incvUi.ajaxurl) || ajaxurl, data)
                .done(function (resp) {
                    $btn.prop('disabled', false);
                    if (resp && resp.success) {
                        $('#incv-quick-status').text(<?php echo wp_json_encode( __( 'Created!', 'incentiv' ) ); ?>);
                        (window.incvPjaxReload || function () { location.reload(); })();
                        $overlay.fadeOut(150);
                    } else {
                        $('#incv-quick-status').text((resp && resp.data && resp.data.message) || <?php echo wp_json_encode( __( 'Creation failed', 'incentiv' ) ); ?>);
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false);
                    $('#incv-quick-status').text(<?php echo wp_json_encode( __( 'Creation failed', 'incentiv' ) ); ?>);
                });
        });
    });
    <?php } ); INCV_Helpers::add_inline_script( $incv_inline_js ); ?>
    <?php
}
?>
