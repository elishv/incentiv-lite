<?php
if (!defined('ABSPATH')) {
    exit;
}

$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.
$rule_set_id = isset($_GET['rule_set_id']) ? sanitize_text_field(wp_unslash($_GET['rule_set_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.

if ($action === 'edit' || $action === 'new') {
    // The edit template includes the wizard overlay markup itself; don't include it here too.
    include MMD_PLUGIN_DIR . 'templates/admin-rule-set-edit.php';
} else {
    // List view
    $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
    $usage_counts = get_option('mmd_rule_usage_counts', array());
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
        'active' => ( '<span class="mmd-pill mmd-pill-success"><span class="mmd-pill-dot"></span>' . esc_html__( 'Active', 'incentiv' ) . '</span>' ),
        'scheduled' => ( '<span class="mmd-pill mmd-pill-warning"><span class="mmd-pill-dot"></span>' . esc_html__( 'Scheduled', 'incentiv' ) . '</span>' ),
        'disabled' => ( '<span class="mmd-pill mmd-pill-muted"><span class="mmd-pill-dot"></span>' . esc_html__( 'Disabled', 'incentiv' ) . '</span>' ),
        'draft' => ( '<span class="mmd-pill mmd-pill-draft"><span class="mmd-pill-dot"></span>' . esc_html__( 'Draft', 'incentiv' ) . '</span>' ),
    );

    $grid_cols = 'grid-template-columns:2.4fr 1fr 1.3fr 1fr .9fr 1.2fr;';
    ?>
    <div class="wrap mmd-admin-wrap">
        <?php include MMD_PLUGIN_DIR . 'templates/admin-nav-tabs.php'; ?>

        <div class="mmd-page-head">
            <div>
                <h2 class="mmd-page-title"><?php esc_html_e( 'Discount rules', 'incentiv' ); ?></h2>
                <div class="mmd-page-sub"><?php echo esc_html(count($rule_sets)); ?> <?php echo wp_kses_post( __( 'Rules &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_active); ?> <?php esc_html_e( 'active', 'incentiv' ); ?></div>
            </div>
            <div class="mmd-head-actions">
                <button type="button" class="mmd-btn mmd-btn-soft-purple" id="mmd-quick-create-open">
                    <span class="msr">bolt</span><?php esc_html_e( 'Quick create', 'incentiv' ); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=new')); ?>" class="mmd-btn mmd-btn-outline">
                    <span class="msr">auto_awesome</span><?php esc_html_e( 'Wizard', 'incentiv' ); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=new&skip_wizard=1')); ?>" class="mmd-btn mmd-btn-primary">
                    <span class="msr">add</span><?php esc_html_e( 'New rule', 'incentiv' ); ?>
                </a>
            </div>
        </div>

        <?php if (empty($rule_sets)): ?>
            <div class="mmd-empty-state">
                <p><?php esc_html_e( 'No discount rules found. Create your first rule!', 'incentiv' ); ?></p>
                <p style="display:flex;gap:10px;justify-content:center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=new')); ?>" class="mmd-btn mmd-btn-primary">
                        <span class="msr">auto_awesome</span><?php esc_html_e( 'Create a rule with the wizard', 'incentiv' ); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=new&skip_wizard=1')); ?>" class="mmd-btn mmd-btn-outline">
                        <?php esc_html_e( 'Create a rule manually', 'incentiv' ); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>

            <!-- Filter chips -->
            <div class="mmd-filter-chips">
                <span class="mmd-chip mmd-chip-active" data-filter="all"><?php echo wp_kses_post( __( 'All &middot;', 'incentiv' ) ); ?> <?php echo esc_html(count($rule_sets)); ?></span>
                <span class="mmd-chip" data-filter="active"><?php echo wp_kses_post( __( 'Active &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_active); ?></span>
                <span class="mmd-chip" data-filter="disabled"><?php echo wp_kses_post( __( 'Disabled &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_disabled); ?></span>
                <span class="mmd-chip" data-filter="scheduled"><?php echo wp_kses_post( __( 'Scheduled &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_scheduled); ?></span>
                <?php if ($count_draft): ?>
                <span class="mmd-chip" data-filter="draft"><?php echo wp_kses_post( __( 'Drafts &middot;', 'incentiv' ) ); ?> <?php echo esc_html($count_draft); ?></span>
                <?php endif; ?>
            </div>

            <!-- Rules table -->
            <div class="mmd-tablecard">
                <div class="mmd-tablecard-head" style="<?php echo esc_attr($grid_cols); ?>">
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
                    $icon_cls = $is_disabled ? 'mmd-row-icon-muted' : ($has_gift ? 'mmd-row-icon-purple' : '');
                    $chip_cls = $is_disabled ? 'mmd-tier-chip-muted' : ($status === 'scheduled' ? 'mmd-tier-chip-orange' : '');

                    // Schedule meta line
                    $meta_line = $id;
                    if ($status === 'scheduled' && !empty($rule_set['schedule_start'])) {
                        $meta_line = ( __( 'Scheduled ·', 'incentiv' ) . ' ' ) . date_i18n(__( 'j M', 'incentiv' ), strtotime($rule_set['schedule_start']));
                    }
                ?>
                <div class="mmd-tablecard-row <?php echo $is_disabled ? 'mmd-row-disabled' : ''; ?>"
                     style="<?php echo esc_attr($grid_cols); ?>" data-mmd-status="<?php echo esc_attr($status); ?>">

                    <div style="display:flex;align-items:center;gap:11px;min-width:0;">
                        <span class="mmd-row-icon <?php echo esc_attr($icon_cls); ?>"><span class="msr"><?php echo esc_html($icon); ?></span></span>
                        <div style="min-width:0;">
                            <div class="mmd-row-title"><?php echo esc_html($rule_set['name']); ?></div>
                            <div class="mmd-row-meta"><?php echo esc_html($meta_line); ?></div>
                        </div>
                    </div>

                    <div><?php echo $status_pills[$status]; // phpcs:ignore ?></div>

                    <div class="mmd-row-method">
                        <span class="msr"><?php echo esc_html($method_info[1]); ?></span><?php echo esc_html($method_info[0]); ?>
                    </div>

                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php
                        $tiers = isset($rule_set['discount_rules']) ? $rule_set['discount_rules'] : array();
                        $shown = 0;
                        foreach ($tiers as $tier) {
                            if ($shown >= 3) {
                                echo '<span class="mmd-tier-chip ' . esc_attr($chip_cls) . '">+' . esc_html(count($tiers) - 3) . '</span>';
                                break;
                            }
                            $is_free = isset($tier['type']) && $tier['type'] === 'percentage' && floatval($tier['discount']) >= 100;
                            echo '<span class="mmd-tier-chip ' . esc_attr($chip_cls ? $chip_cls : ($is_free ? 'mmd-tier-chip-purple' : '')) . '">' . esc_html(intval($tier['quantity'])) . '+' . ($is_free ? ' 🎁' : '') . '</span>';
                            $shown++;
                        }
                        if (empty($tiers)) {
                            echo '<span class="mmd-tier-chip mmd-tier-chip-muted">—</span>';
                        }
                        ?>
                    </div>

                    <div class="mmd-usagebars" title="<?php echo esc_attr($usage . ( ' ' . esc_html__( 'Uses', 'incentiv' ) )); ?>">
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
                    <div class="mmd-row-actions">
                        <button type="button" class="mmd-icon-btn mmd-toggle-rule <?php echo $rule_on ? 'mmd-icon-btn-active' : ''; ?>"
                                title="<?php echo $rule_on ? esc_html__( 'Pause', 'incentiv' ) : esc_html__( 'Activate', 'incentiv' ); ?>"
                                data-rule-id="<?php echo esc_attr($id); ?>"
                                data-enabled="<?php echo $rule_on ? 'yes' : 'no'; ?>">
                            <span class="msr"><?php echo $rule_on ? 'pause' : 'play_arrow'; ?></span>
                        </button>
                        <a class="mmd-icon-btn" title="<?php esc_attr_e( 'Edit', 'incentiv' ); ?>"
                           href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=edit&rule_set_id=' . urlencode($id))); ?>">
                            <span class="msr">edit</span>
                        </a>
                        <a class="mmd-icon-btn" title="<?php esc_attr_e( 'Duplicate', 'incentiv' ); ?>"
                           href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=mix-match-discounts&action=duplicate&rule_set_id=' . urlencode($id)), 'mmd_duplicate_rule')); ?>">
                            <span class="msr">content_copy</span>
                        </a>
                        <a class="mmd-icon-btn mmd-icon-btn-danger" title="<?php esc_attr_e( 'Delete', 'incentiv' ); ?>"
                           href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=mix-match-discounts&action=delete&rule_set_id=' . urlencode($id)), 'mmd_delete_rule')); ?>"
                           onclick="return confirm('<?php echo esc_attr_x( 'Are you sure you want to delete this rule?', 'list', 'incentiv' ); ?>');">
                            <span class="msr">delete</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Example rules gallery -->
        <?php include MMD_PLUGIN_DIR . 'templates/admin-example-rules.php'; ?>

        <div class="mmd-tips-box">
            <h3><span class="msr" style="color:var(--mmd-primary);">lightbulb</span> <?php esc_html_e( 'Tips', 'incentiv' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'Each discount rule works independently. You can set different rules for different products or categories', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'If a product meets the conditions of several rules, the first rule found applies', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'Use the duplicate option to create similar rules quickly', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'You can combine included categories with excluding specific products from those categories', 'incentiv' ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>New:</strong> the dashboard shows the real performance of every rule. Track sales in real time', 'incentiv' ) ); ?></li>
            </ul>
        </div>

        <!-- ================= Quick create ================= -->
        <?php $quick_presets = MMD_Admin::get_quick_presets(); ?>
        <div id="mmd-quick-create" class="mmd-quick-overlay" style="display:none;">
            <div class="mmd-quick-backdrop"></div>
            <div class="mmd-quick-dialog" role="dialog" aria-modal="true" aria-labelledby="mmd-quick-title">
                <div class="mmd-quick-head">
                    <span class="msr">bolt</span>
                    <div>
                        <h2 id="mmd-quick-title"><?php esc_html_e( 'Quick create', 'incentiv' ); ?></h2>
                        <div class="mmd-quick-sub"><?php esc_html_e( 'Choose a template, fill in a number or two, and the rule is live.', 'incentiv' ); ?></div>
                    </div>
                    <button type="button" class="mmd-icon-btn" id="mmd-quick-close" title="<?php esc_attr_e( 'Close', 'incentiv' ); ?>"><span class="msr">close</span></button>
                </div>

                <div class="mmd-quick-body">
                    <div class="mmd-quick-presets">
                        <?php $first = true; foreach ($quick_presets as $key => $preset): ?>
                            <button type="button" class="mmd-quick-card<?php echo $first ? ' is-active' : ''; ?>" data-preset="<?php echo esc_attr($key); ?>">
                                <span class="msr"><?php echo esc_html($preset['icon']); ?></span>
                                <strong><?php echo esc_html($preset['title']); ?></strong>
                                <span><?php echo esc_html($preset['desc']); ?></span>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </div>

                    <?php foreach ($quick_presets as $key => $preset): ?>
                        <div class="mmd-quick-fields" data-preset="<?php echo esc_attr($key); ?>" style="<?php echo $key === array_key_first($quick_presets) ? '' : 'display:none;'; ?>">
                            <div class="mmd-quick-field">
                                <label for="mmd-quick-name-<?php echo esc_attr($key); ?>"><?php esc_html_e( 'Rule name', 'incentiv' ); ?></label>
                                <input type="text" id="mmd-quick-name-<?php echo esc_attr($key); ?>" class="mmd-quick-name"
                                       placeholder="<?php echo esc_attr($preset['title']); ?>">
                            </div>
                            <?php foreach ($preset['fields'] as $fkey => $field): ?>
                                <div class="mmd-quick-field">
                                    <label for="mmd-quick-<?php echo esc_attr($key . '-' . $fkey); ?>"><?php echo esc_html($field['label']); ?></label>
                                    <div class="mmd-quick-input">
                                        <input type="number" id="mmd-quick-<?php echo esc_attr($key . '-' . $fkey); ?>"
                                               data-field="<?php echo esc_attr($fkey); ?>"
                                               value="<?php echo esc_attr($field['default']); ?>"
                                               <?php echo isset($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : ''; ?>
                                               <?php echo isset($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : ''; ?>>
                                        <?php if (!empty($field['suffix'])): ?>
                                            <span class="mmd-quick-suffix"><?php echo esc_html($field['suffix']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="mmd-card-tip" style="margin:4px 0 0;">
                        <?php esc_html_e( 'The rule is created for all products and is active immediately. You can narrow it to categories, add conditions or change the discount split when editing.', 'incentiv' ); ?>
                    </div>
                </div>

                <div class="mmd-quick-foot">
                    <span id="mmd-quick-status" class="mmd-draft-status"></span>
                    <button type="button" class="mmd-btn mmd-btn-ghost" id="mmd-quick-cancel"><?php esc_html_e( 'Cancel', 'incentiv' ); ?></button>
                    <button type="button" class="mmd-btn mmd-btn-green" id="mmd-quick-submit"><span class="msr">check</span><?php esc_html_e( 'Create rule', 'incentiv' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(function ($) {
        var $overlay = $('#mmd-quick-create');

        function activePreset() { return $('.mmd-quick-card.is-active').data('preset'); }

        $(document).on('click', '#mmd-quick-create-open', function () { $overlay.fadeIn(150); });
        $(document).on('click', '#mmd-quick-close, #mmd-quick-cancel, .mmd-quick-backdrop', function () { $overlay.fadeOut(150); });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) { $overlay.fadeOut(150); }
        });

        $(document).on('click', '.mmd-quick-card', function () {
            var preset = $(this).data('preset');
            $('.mmd-quick-card').removeClass('is-active');
            $(this).addClass('is-active');
            $('.mmd-quick-fields').hide().filter('[data-preset="' + preset + '"]').show();
        });

        $(document).on('click', '#mmd-quick-submit', function () {
            var $btn = $(this).prop('disabled', true);
            var preset = activePreset();
            var $fields = $('.mmd-quick-fields[data-preset="' + preset + '"]');

            var data = {
                action: 'mmd_quick_create_rule',
                nonce: (window.mmdUi && mmdUi.nonce) || '',
                preset: preset,
                name: $fields.find('.mmd-quick-name').val() || ''
            };
            $fields.find('input[data-field]').each(function () {
                data[$(this).data('field')] = $(this).val();
            });

            $('#mmd-quick-status').text(<?php echo wp_json_encode( __( 'Creating…', 'incentiv' ) ); ?>).addClass('is-visible');

            $.post((window.mmdUi && mmdUi.ajaxurl) || ajaxurl, data)
                .done(function (resp) {
                    $btn.prop('disabled', false);
                    if (resp && resp.success) {
                        $('#mmd-quick-status').text(<?php echo wp_json_encode( __( 'Created!', 'incentiv' ) ); ?>);
                        (window.mmdPjaxReload || function () { location.reload(); })();
                        $overlay.fadeOut(150);
                    } else {
                        $('#mmd-quick-status').text((resp && resp.data && resp.data.message) || <?php echo wp_json_encode( __( 'Creation failed', 'incentiv' ) ); ?>);
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false);
                    $('#mmd-quick-status').text(<?php echo wp_json_encode( __( 'Creation failed', 'incentiv' ) ); ?>);
                });
        });
    });
    </script>
    <?php
}
?>
