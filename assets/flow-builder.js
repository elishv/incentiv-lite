/**
 * Incentiv Flow Builder — interactive canvas.
 * Nodes are composed on the canvas, configured inline, and compiled into
 * real discount rules / gift rules / coupons via mmd_create_flow.
 */
(function ($) {
    'use strict';
    var __ = wp.i18n.__;
    var _x = wp.i18n._x;

    var NODE_DEFS = {
        trigger_qty: {
            icon: 'shopping_cart', cls: 'mmd-flownode-quantity',
            title: __( 'Customer adds a quantity', 'incentiv' ), sub: __( 'Trigger', 'incentiv' )
        },
        trigger_total: {
            icon: 'payments', cls: '',
            title: __( 'Cart total', 'incentiv' ), sub: __( 'Trigger', 'incentiv' )
        },
        trigger_coupon: {
            icon: 'confirmation_number', cls: 'mmd-flownode-coupon',
            title: __( 'Customer enters a code', 'incentiv' ), sub: __( 'Customer interaction', 'incentiv' )
        },
        trigger_role: {
            icon: 'group', cls: 'mmd-flownode-event',
            title: __( 'Customer type', 'incentiv' ), sub: __( 'Trigger', 'incentiv' )
        },
        action_discount: {
            icon: 'percent', cls: 'mmd-flownode-success',
            title: __( 'Discount', 'incentiv' ), sub: __( 'Action', 'incentiv' )
        },
        action_gift_choice: {
            icon: 'touch_app', cls: 'mmd-flownode-interaction',
            title: __( 'Customer chooses a gift', 'incentiv' ), sub: __( 'Customer interaction', 'incentiv' )
        },
        action_gift_auto: {
            icon: 'card_giftcard', cls: 'mmd-flownode-gift',
            title: __( 'Automatic gift', 'incentiv' ), sub: __( 'Action', 'incentiv' )
        }
    };

    var flowNodes = [];
    var uid = 0;

    function catOptions(selected) {
        var html = '';
        (window.mmdFlow && mmdFlow.categories || []).forEach(function (c) {
            var sel = (selected || []).indexOf(String(c.id)) !== -1 ? ' selected' : '';
            html += '<option value="' + c.id + '"' + sel + '>' + $('<i>').text(c.name).html() + '</option>';
        });
        return html;
    }

    function productOptions(selected) {
        var html = '';
        (window.mmdFlow && mmdFlow.products || []).forEach(function (p) {
            var sel = (selected || []).indexOf(String(p.id)) !== -1 ? ' selected' : '';
            html += '<option value="' + p.id + '"' + sel + '>' + $('<i>').text(p.name).html() + '</option>';
        });
        return html;
    }

    function roleOptions(selected) {
        var roles = (window.mmdFlow && mmdFlow.roles) || [
            { id: 'customer', name: __( 'Customer', 'incentiv' ) },
            { id: 'subscriber', name: __( 'Subscriber', 'incentiv' ) }
        ];
        var html = '';
        roles.forEach(function (r) {
            var sel = (selected || []).indexOf(String(r.id)) !== -1 ? ' selected' : '';
            html += '<option value="' + r.id + '"' + sel + '>' + $('<i>').text(r.name).html() + '</option>';
        });
        return html;
    }

    function isTrigger(n) { return n.type.indexOf('trigger_') === 0; }
    function isAction(n) { return n.type.indexOf('action_') === 0; }

    function field(label, control) {
        return '<div class="mmd-flow-field"><label>' + label + '</label>' + control + '</div>';
    }

    function nodeConfigHtml(node) {
        var cfg = node.cfg;
        switch (node.type) {
            case 'trigger_qty':
                return field(__( 'Minimum product quantity (≥)', 'incentiv' ), '<input type="number" min="1" data-k="qty" value="' + (cfg.qty || 3) + '">') +
                    field(__( 'Participating categories (empty = whole store)', 'incentiv' ), '<select multiple data-k="categories" size="5">' + catOptions(cfg.categories) + '</select>');
            case 'trigger_total':
                return field(__( 'Minimum cart total (₪)', 'incentiv' ), '<input type="number" min="0" data-k="min" value="' + (cfg.min || 200) + '">');
            case 'trigger_coupon':
                return field(__( 'Coupon code the customer enters', 'incentiv' ), '<input type="text" data-k="code" value="' + (cfg.code || '') + '" placeholder="COFFEE30" style="text-transform:uppercase;direction:ltr;">');
            case 'trigger_role':
                return field(__( 'User roles (empty = all customers)', 'incentiv' ), '<select multiple data-k="roles" size="4">' + roleOptions(cfg.roles) + '</select>');
            case 'action_discount':
                return field(__( 'From a quantity of', 'incentiv' ), '<input type="number" min="1" data-k="qty" value="' + (cfg.qty || 3) + '">') +
                    field(__( 'Discount size', 'incentiv' ), '<input type="number" min="0" data-k="amount" value="' + (cfg.amount || 30) + '">') +
                    field(__( 'Discount type', 'incentiv' ), '<select data-k="kind"><option value="percentage"' + (cfg.kind !== 'fixed' ? ' selected' : '') + ( '>' + __( 'Percentage (%)', 'incentiv' ) + '</option><option value="fixed"' ) + (cfg.kind === 'fixed' ? ' selected' : '') + ( '>' + __( 'Fixed amount (₪)', 'incentiv' ) + '</option></select>' )) +
                    field(__( 'Applies to', 'incentiv' ), '<select data-k="target"><option value="cheapest"' + (cfg.target !== 'all' ? ' selected' : '') + ( '>' + __( 'The cheapest item', 'incentiv' ) + '</option><option value="all"' ) + (cfg.target === 'all' ? ' selected' : '') + ( '>' + __( 'All participating items', 'incentiv' ) + '</option></select>' ));
            case 'action_gift_choice':
            case 'action_gift_auto':
                return field(__( 'Gift products', 'incentiv' ), '<select multiple data-k="products" size="6">' + productOptions(cfg.products) + '</select>') +
                    field(__( 'Maximum number of gifts', 'incentiv' ), '<input type="number" min="1" data-k="limit" value="' + (cfg.limit || 1) + '">');
        }
        return '';
    }

    function nodeSummary(node) {
        var cfg = node.cfg;
        switch (node.type) {
            case 'trigger_qty':
                return (cfg.qty || 3) + __( '+ items', 'incentiv' ) + (cfg.categories && cfg.categories.length ? ' · ' + cfg.categories.length + ( ' ' + __( 'Categories', 'incentiv' ) ) : ( ' ' + __( '· Whole store', 'incentiv' ) ));
            case 'trigger_total':
                return __( 'Cart over ₪', 'incentiv' ) + (cfg.min || 200);
            case 'trigger_coupon':
                return cfg.code ? ( __( 'Code', 'incentiv' ) + ' ' ) + cfg.code : __( 'Coupon code', 'incentiv' );
            case 'trigger_role':
                return (cfg.roles && cfg.roles.length) ? cfg.roles.length + ( ' ' + __( 'Roles', 'incentiv' ) ) : __( 'All customers', 'incentiv' );
            case 'action_discount':
                return (cfg.qty || 3) + '+' + (document.documentElement.dir === 'rtl' ? ' ← ' : ' → ') + (cfg.amount || 30) + (cfg.kind === 'fixed' ? ' ₪' : '%') + (cfg.target === 'all' ? ( ' ' + __( 'on everything', 'incentiv' ) ) : ( ' ' + __( 'on the cheapest', 'incentiv' ) ));
            case 'action_gift_choice':
                return (cfg.products && cfg.products.length ? cfg.products.length + ( ' ' + __( 'Products to choose from', 'incentiv' ) ) : __( 'Choose from a list', 'incentiv' ));
            case 'action_gift_auto':
                return (cfg.products && cfg.products.length ? cfg.products.length + ( ' ' + _x( 'Gift products', 'flow', 'incentiv' ) ) : __( 'Added automatically', 'incentiv' ));
        }
        return '';
    }

    var activeConfigId = null;

    function arrowHtml() {
        return '<span class="mmd-flow-arrow"><span class="msr mmd-dir-icon">arrow_back</span></span>';
    }

    function startHtml() {
        return '<div class="mmd-flow-node mmd-flownode-start"><span class="msr">person</span>' +
            ( '<div><div class="mmd-flownode-title">' + __( 'Customer', 'incentiv' ) + '</div><div class="mmd-flownode-sub">' + __( 'In the store', 'incentiv' ) + '</div></div></div>' );
    }

    function endHtml(compact) {
        return '<div class="mmd-flow-node mmd-flownode-end' + (compact ? ' mmd-flownode-outcome' : '') + '"><span class="msr">check_circle</span>' +
            ( '<div><div class="mmd-flownode-title">' + __( 'A fuller cart', 'incentiv' ) + '</div>' ) +
            (compact ? '' : ( '<div class="mmd-flownode-sub">' + __( 'Every reason to buy more', 'incentiv' ) + '</div>' )) + '</div></div>';
    }

    function splitHtml(count) {
        return '<div class="mmd-flow-node mmd-flow-split"><span class="msr">call_split</span>' +
            ( '<div><div class="mmd-flownode-title">' + __( 'Split paths', 'incentiv' ) + '</div>' ) +
            '<div class="mmd-flownode-sub">' + count + ( ' ' + __( 'Parallel paths', 'incentiv' ) + '</div></div></div>' );
    }

    function userNodeHtml(node) {
        var def = NODE_DEFS[node.type];
        return '<div class="mmd-flow-node mmd-flownode-user ' + def.cls + (node.id === activeConfigId ? ' is-config' : '') + '" data-id="' + node.id + '" tabindex="0">' +
            '<span class="msr">' + def.icon + '</span>' +
            '<div><div class="mmd-flownode-title">' + def.title + '</div>' +
            '<div class="mmd-flownode-sub">' + nodeSummary(node) + '</div></div>' +
            ( '<button type="button" class="mmd-flownode-remove" title="' + __( 'Remove', 'incentiv' ) + '">✕</button>' ) +
            ( '<span class="mmd-flownode-gear msr" title="' + __( 'Settings', 'incentiv' ) + '">tune</span>' ) +
            '</div>';
    }

    function render() {
        var $canvas = $('#mmd-flow-canvas');
        if (!$canvas.length) { return; }

        if (!flowNodes.length) {
            $canvas.html('<div class="mmd-flow-trunk">' + startHtml() +
                ( '<div id="mmd-flow-empty" style="color:var(--mmd-text-light);font-size:13px;padding:14px;">' + __( 'Add steps from the palette above ↑', 'incentiv' ) + '</div></div>' ));
            closeConfig();
            return;
        }

        var triggers = flowNodes.filter(isTrigger);
        var actions = flowNodes.filter(isAction);

        var trunk = startHtml();
        triggers.forEach(function (t) { trunk += arrowHtml() + userNodeHtml(t); });

        var html;
        if (actions.length >= 2) {
            // Branch: each action is a parallel path fanning out from a split node
            trunk += arrowHtml() + splitHtml(actions.length);
            var branches = '';
            actions.forEach(function (a, i) {
                branches += '<div class="mmd-flow-branch">' +
                    ( '<span class="mmd-flow-branch-label">' + __( 'Path', 'incentiv' ) + ' ' ) + (i + 1) + '</span>' +
                    userNodeHtml(a) + arrowHtml() + endHtml(true) +
                    '</div>';
            });
            html = '<div class="mmd-flow-trunk">' + trunk + '</div>' +
                '<div class="mmd-flow-branches">' + branches + '</div>';
        } else {
            // Linear: single path
            var chain = trunk;
            actions.forEach(function (a) { chain += arrowHtml() + userNodeHtml(a); });
            chain += arrowHtml() + endHtml(false);
            html = '<div class="mmd-flow-trunk">' + chain + '</div>';
        }

        $canvas.html(html);
    }

    function findNode(id) {
        return flowNodes.filter(function (n) { return n.id === id; })[0];
    }

    function openConfig(id) {
        var node = findNode(id);
        if (!node) { return; }
        activeConfigId = id;
        var def = NODE_DEFS[node.type];
        $('#mmd-flow-config-icon').text(def.icon);
        $('#mmd-flow-config-title').text(( __( 'Settings ·', 'incentiv' ) + ' ' ) + def.title);
        $('#mmd-flow-config-body').html(nodeConfigHtml(node));
        $('#mmd-flow-config-panel').slideDown(150);
        $('.mmd-flow-node').removeClass('is-config');
        $('.mmd-flow-node[data-id="' + id + '"]').addClass('is-config');
    }

    function closeConfig() {
        activeConfigId = null;
        $('#mmd-flow-config-panel').slideUp(150);
        $('.mmd-flow-node').removeClass('is-config');
    }

    $(document).on('click', '.mmd-flow-add', function () {
        var type = $(this).data('node');
        if (!NODE_DEFS[type]) { return; }
        // Only one of each trigger type
        if (type.indexOf('trigger_') === 0 && flowNodes.some(function (n) { return n.type === type; })) {
            return;
        }
        // A flow has one gift action
        if (type.indexOf('action_gift') === 0 && flowNodes.some(function (n) { return n.type.indexOf('action_gift') === 0; })) {
            return;
        }
        var newId = ++uid;
        flowNodes.push({ id: newId, type: type, cfg: {} });
        // Triggers first, then actions — keep journey order sensible
        flowNodes.sort(function (a, b) {
            var ta = a.type.indexOf('trigger_') === 0 ? 0 : 1;
            var tb = b.type.indexOf('trigger_') === 0 ? 0 : 1;
            return ta - tb || a.id - b.id;
        });
        render();
        openConfig(newId); // reveal its settings straight away
    });

    $(document).on('click', '.mmd-flownode-remove', function (e) {
        e.stopPropagation();
        var id = $(this).closest('.mmd-flow-node').data('id');
        if (id === activeConfigId) { closeConfig(); }
        flowNodes = flowNodes.filter(function (n) { return n.id !== id; });
        render();
    });

    // Open the config panel for a node
    $(document).on('click', '.mmd-flownode-user', function (e) {
        if ($(e.target).closest('.mmd-flownode-remove').length) { return; }
        openConfig($(this).data('id'));
    });

    $(document).on('click', '#mmd-flow-config-close', function () { closeConfig(); });

    // Sync config inputs into state + live node summary
    $(document).on('input change', '#mmd-flow-config-body [data-k]', function () {
        var node = findNode(activeConfigId);
        if (!node) { return; }
        var $el = $(this);
        var key = $el.data('k');
        if ($el.is('select[multiple]')) {
            node.cfg[key] = ($el.val() || []).map(String);
        } else {
            node.cfg[key] = $el.val();
        }
        $('.mmd-flow-node[data-id="' + node.id + '"] .mmd-flownode-sub').first().text(nodeSummary(node));
    });

    // Create the flow
    $(document).on('click', '#mmd-flow-create', function () {
        var $btn = $(this);
        var name = $('#mmd-flow-name').val().trim();
        var $result = $('#mmd-flow-result');

        if (!name) {
            $result.show().html(( '<div class="mmd-card-tip" style="border-right-color:#f04438;background:#feecea;">' + __( 'Please give the flow a name.', 'incentiv' ) + '</div>' ));
            return;
        }
        if (!flowNodes.length) {
            $result.show().html(( '<div class="mmd-card-tip" style="border-right-color:#f04438;background:#feecea;">' + __( 'The flow is empty. Add steps from the palette.', 'incentiv' ) + '</div>' ));
            return;
        }

        $btn.prop('disabled', true);
        $.post(mmdFlow.ajaxurl, {
            action: 'mmd_create_flow',
            nonce: mmdFlow.nonce,
            name: name,
            nodes: JSON.stringify(flowNodes.map(function (n) { return { type: n.type, cfg: n.cfg }; }))
        }).done(function (resp) {
            $btn.prop('disabled', false);
            if (!resp || !resp.success) {
                $result.show().html('<div class="mmd-card-tip" style="border-right-color:#f04438;background:#feecea;">' + ((resp && resp.data) || __( 'Error creating the flow', 'incentiv' )) + '</div>');
                return;
            }
            var d = resp.data;
            var links = '';
            if (d.edit_rule_url) { links += '<a class="mmd-btn mmd-btn-outline" href="' + d.edit_rule_url + ( '">' + __( 'Edit the discount rule', 'incentiv' ) + '</a> ' ); }
            if (d.edit_gift_url) { links += '<a class="mmd-btn mmd-btn-outline" href="' + d.edit_gift_url + ( '">' + __( 'Edit the gift rule', 'incentiv' ) + '</a> ' ); }
            if (d.auto_apply_url) { links += '<a class="mmd-btn mmd-btn-outline" href="' + d.auto_apply_url + ( '" target="_blank" rel="noopener">' + __( 'Auto-apply link', 'incentiv' ) + '</a> ' ); }
            $result.show().html(
                '<div style="background:var(--mmd-success-light);border:1px solid var(--mmd-success);border-radius:12px;padding:18px;">' +
                ( '<div style="font-weight:800;color:var(--mmd-success);font-size:15px;margin-bottom:8px;">' + __( '🎉 Flow created!', 'incentiv' ) + '</div>' ) +
                ( '<div style="font-size:13px;color:var(--mmd-text-label);margin-bottom:12px;">' + __( 'Incentiv created everything for you. The flow is already live in your store.', 'incentiv' ) + '</div>' ) +
                '<div style="display:flex;gap:8px;flex-wrap:wrap;">' + links + '</div>' +
                '</div>'
            );
            flowNodes = [];
            render();
            $('#mmd-flow-name').val('');
        }).fail(function () {
            $btn.prop('disabled', false);
            $result.show().html(( '<div class="mmd-card-tip" style="border-right-color:#f04438;background:#feecea;">' + __( 'Connection error. Please try again.', 'incentiv' ) + '</div>' ));
        });
    });

    $(render);
    $(document).on('mmd:pjax:after', function () {
        if ($('#mmd-flow-canvas').length) { flowNodes = []; render(); }
    });
})(jQuery);
