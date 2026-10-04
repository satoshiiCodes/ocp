<?php
/**
 * includes/routing_notifications.php
 *
 * "You have an action waiting" for the two routing systems, in one place.
 *
 * A request sits on a stage until somebody acts on it, and which somebody is decided by
 * department/position/accounttype - the same test the routing handlers make before they will
 * run an action (see the `$current_stage['stage'] === ... && $user[...]` guards in
 * actions/pr_view_routing-actions.php and actions/pr_spare_view_routing-actions.php).
 * THE RULES BELOW ARE THAT SAME TABLE. If a guard there changes, change the matching row
 * here, or people are told about work they cannot do - or not told about work they can.
 *
 * Two things this deliberately does NOT assume:
 *
 *   - The table shape. The bell's original query read prr.purchase_request_id, prr.current_step,
 *     prr.current_status and prr.updated_at, and pr.purchase_request_no / pr.created_by. None of
 *     those columns exist: pr_routing holds pr_id, stage, status, created_at, and the PR table
 *     holds pr_number and requested_by. Every send of that query raised, the catch turned it
 *     into "no notifications", and the bell has been permanently empty. The real columns are
 *     used here.
 *   - The stage names. The old code carried a 5-step model ('Warehouse Department',
 *     'Purchasing Department', ...) that no handler uses. The stages below are the ones
 *     actually written to pr_routing.stage / spare_parts_pr_routing.stage.
 *
 * The result is a flat list, newest first, ready to render or to serialise into an SSE frame.
 */

if (defined('OCP_ROUTING_NOTIFICATIONS_LOADED')) {
    return;
}
define('OCP_ROUTING_NOTIFICATIONS_LOADED', true);

/**
 * The stage each routing source can hand to a user, and the criteria that user must meet.
 *
 * A source entry is one (table, request table, label) triple. Each stage entry is either:
 *   'criteria' => [ [department, position, accounttype], ... ]   any one match is enough
 *   'self'     => true                                           only the requester, tested per row
 *
 * A null criterion means "don't care" for that column, matching how the handlers compare:
 * the spare warehouse guards test department and accounttype but never position.
 *
 * The 'requestor' stage is deliberately NOT listed. It is where a request sits before it has
 * been forwarded, and nothing writes a routing row for that - a request with no routing row at
 * all is treated as requestor (see the fallback in actions/pr_view_routing-actions.php), so
 * there are no pending requestor rows to find. Listing it would have put a bell in front of
 * every user in the system with nothing to say, since every user can raise a request.
 */
function ocp_routing_notification_sources() {
    $warehouse = [['department' => 'Warehouse', 'position' => null, 'accounttype' => 'Admin']];
    $motorpool = [['department' => 'Motorpool', 'position' => null, 'accounttype' => 'Admin']];
    $purchaser = [['department' => 'Admin', 'position' => 'Purchaser', 'accounttype' => 'Admin']];
    $accounting = [['department' => 'Admin', 'position' => 'Accounting', 'accounttype' => 'Admin']];
    $ceo = [['department' => 'Admin', 'position' => 'CEO', 'accounttype' => 'Admin']];

    return [
        'pr' => [
            'table' => 'pr_routing',
            'pr_table' => 'purchase_requests',
            'label' => 'Purchase Request',
            // URI is a JavaScript template; __PR__ is replaced with the request id.
            'uri' => 'pr_view_routing.php?id=__PR__',
            'stages' => [
                'warehouse' => ['criteria' => $warehouse],
                'purchasing' => ['criteria' => $purchaser],
                'accounting' => ['criteria' => $accounting],
                'approver' => ['criteria' => $ceo],
                'purchasing_final' => ['criteria' => $purchaser],
                'warehouse_receiving' => ['criteria' => $warehouse],
                'warehouse_releasing' => ['criteria' => $warehouse],
                'purchasing_completion' => ['criteria' => $purchaser],
                'accounting_final' => ['criteria' => $accounting],
                // Not in the handlers' guard list, but present in pr_routing.stage as written
                // by complete_warehouse_releasing; treat it as the same warehouse work so a
                // request parked here is not silently invisible.
                'complete_warehouse_releasing' => ['criteria' => $warehouse],
            ],
        ],
        'spare' => [
            'table' => 'spare_parts_pr_routing',
            'pr_table' => 'spare_parts_pr',
            'label' => 'Spare Parts Request',
            'uri' => 'pr_spare_view_routing.php?id=__PR__',
            'stages' => [
                // Spare parts are held by Motorpool, not the main warehouse.
                'warehouse' => ['criteria' => $motorpool],
                'purchasing' => ['criteria' => $purchaser],
                'approver' => ['criteria' => $ceo],
                'warehouse_receiving' => ['criteria' => $motorpool],
                'warehouse_releasing' => ['criteria' => $motorpool],
            ],
        ],
    ];
}

/**
 * Human labels for the stages, for the notification body.
 */
function ocp_routing_stage_labels() {
    return [
        'requestor' => 'Requestor - needs submitting',
        'warehouse' => 'Warehouse - for checking',
        'purchasing' => 'Purchasing - for processing',
        'accounting' => 'Accounting - for processing',
        'approver' => 'Approver (CEO) - for approval',
        'purchasing_final' => 'Purchasing Final - for processing',
        'warehouse_receiving' => 'Warehouse Receiving',
        'warehouse_releasing' => 'Warehouse Releasing',
        'purchasing_completion' => 'Purchasing Completion',
        'accounting_final' => 'Accounting Final - for finalizing',
        'complete_warehouse_releasing' => 'Warehouse Releasing - for completion',
    ];
}

/**
 * Which sources, and which stages within them, this user may act on.
 *
 * Mirrors the handler guards. Returns [sourceKey => ['stages' => [...], 'self' => bool]].
 * 'self' marks a source whose requestor stage belongs to the user, so the caller can add the
 * requested_by test to that query.
 */
function ocp_routing_user_access($user) {
    $department = $user['department'] ?? '';
    $position = $user['position'] ?? '';
    $accounttype = $user['accounttype'] ?? '';

    $sources = ocp_routing_notification_sources();
    $access = [];

    foreach ($sources as $key => $source) {
        $stages = [];
        foreach ($source['stages'] as $stage => $rule) {
            if (!empty($rule['self'])) {
                // Whose request it is can only be known per row; the caller filters.
                $stages[] = $stage;
                continue;
            }
            foreach ($rule['criteria'] as $criterion) {
                $matches = ($criterion['department'] === null || $criterion['department'] === $department)
                    && ($criterion['position'] === null || $criterion['position'] === $position)
                    && ($criterion['accounttype'] === null || $criterion['accounttype'] === $accounttype);
                if ($matches) {
                    $stages[] = $stage;
                    break;
                }
            }
        }
        if (!empty($stages)) {
            $access[$key] = ['stages' => $stages];
        }
    }

    return $access;
}

/**
 * Every request waiting on this user, newest routing change first.
 *
 * Each row: source, pr_id, uri, reference, title, description, creator, stage, stage_label,
 * created_at, routing_at, age_seconds.
 *
 * @param PDO   $pdo
 * @param array $user  needs department, position, accounttype
 * @param int   $user_id
 * @param int   $limit
 */
function ocp_routing_pending_notifications($pdo, $user, $user_id, $limit = 15) {
    $access = ocp_routing_user_access($user);
    if (empty($access)) {
        return [];
    }

    $sources = ocp_routing_notification_sources();
    $labels = ocp_routing_stage_labels();
    $notifications = [];

    foreach ($access as $key => $granted) {
        $source = $sources[$key];
        $stages = $granted['stages'];
        $placeholders = implode(',', array_fill(0, count($stages), '?'));

        // A request is "on" the newest routing row it has - row id, not created_at, because
        // created_at is only second-resolution and a request moved twice in one second would
        // otherwise be judged by whichever row the engine happened to return first.
        //
        // Only 'pending' wakes anyone: an 'approved' or 'completed' routing row is a stage that
        // has already been dealt with. spare_parts_pr_routing.status is a varchar, so this is a
        // plain string compare rather than an enum test.
        //
        // The requestor stage is the requester's own unfinished submission, so it is filtered to
        // their own requests - which the handlers do too, with $pr['requested_by'] == $user_id.
        $sql = "
            SELECT
                r.pr_id,
                r.stage,
                r.status,
                r.created_at AS routing_at,
                pr.pr_number,
                pr.request_date,
                pr.created_at,
                pr.requested_by,
                u.firstname,
                u.middlename,
                u.lastname,
                u.suffix
            FROM {$source['table']} r
            INNER JOIN (
                SELECT pr_id, MAX(id) AS max_id
                FROM {$source['table']}
                GROUP BY pr_id
            ) newest ON newest.max_id = r.id
            INNER JOIN {$source['pr_table']} pr ON pr.id = r.pr_id
            LEFT JOIN users u ON pr.requested_by = u.id
            WHERE r.stage IN ($placeholders)
              AND r.status = 'pending'
        ";

        $params = $stages;

        // Only apply the requester restriction when the requestor stage is actually involved;
        // for every other stage the row belongs to whoever holds the stage.
        $selfStages = [];
        foreach ($stages as $stage) {
            if (!empty($source['stages'][$stage]['self'])) {
                $selfStages[] = $stage;
            }
        }
        if (!empty($selfStages) && count($selfStages) === count($stages)) {
            // The user's ONLY claim to this source is their own requests.
            $sql .= " AND pr.requested_by = ?";
            $params[] = $user_id;
        } elseif (!empty($selfStages)) {
            // Mixed: own submissions plus stages they serve. Keep their own, plus anything
            // parked on a stage they are responsible for (not their own request).
            $nonSelf = array_values(array_diff($stages, $selfStages));
            $nonSelfPlaceholders = implode(',', array_fill(0, count($nonSelf), '?'));
            $sql .= " AND (pr.requested_by = ? OR r.stage IN ($nonSelfPlaceholders))";
            $params[] = $user_id;
            foreach ($nonSelf as $stage) {
                $params[] = $stage;
            }
        }

        $sql .= " ORDER BY r.created_at DESC, r.id DESC LIMIT " . (int) $limit;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Routing notification query failed (' . $key . '): ' . $e->getMessage());
            continue;
        }

        foreach ($rows as $row) {
            $creator = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
            if ($creator === '') {
                $creator = 'Unknown';
            } else {
                $creator = trim(($row['firstname'] ?? '') . ' '
                    . (!empty($row['middlename']) ? substr($row['middlename'], 0, 1) . '. ' : '')
                    . ($row['lastname'] ?? '') . ' ' . ($row['suffix'] ?? ''));
            }

            $stage = (string) $row['stage'];
            $routingAt = $row['routing_at'] ?? null;
            $age = $routingAt ? max(0, time() - strtotime($routingAt)) : null;

            $notifications[] = [
                'source' => $key,
                'label' => $source['label'],
                'pr_id' => (int) $row['pr_id'],
                'uri' => str_replace('__PR__', (string) $row['pr_id'], $source['uri']),
                'reference' => $row['pr_number'] ?? ('#' . $row['pr_id']),
                'creator' => $creator,
                'stage' => $stage,
                'stage_label' => $labels[$stage] ?? ucwords(str_replace('_', ' ', $stage)),
                'created_at' => $row['created_at'] ?? null,
                'request_date' => $row['request_date'] ?? null,
                'routing_at' => $routingAt,
                'age_seconds' => $age,
                '_sort' => $routingAt ? strtotime($routingAt) : 0,
            ];
        }
    }

    // Newest routing change first, across both sources.
    usort($notifications, function ($a, $b) {
        if ($a['_sort'] === $b['_sort']) {
            return $b['pr_id'] <=> $a['pr_id'];
        }
        return $b['_sort'] <=> $a['_sort'];
    });

    $notifications = array_slice($notifications, 0, $limit);
    foreach ($notifications as &$n) {
        unset($n['_sort']);
    }
    unset($n);

    return $notifications;
}

/**
 * The URL path the browser should open the SSE stream on.
 *
 * Worked out from the running script's own path rather than hard-coded, so the app is right
 * whether it is served from a vhost root or from a subdirectory. The running script is always
 * a page at the application root (it includes includes/top_bar.php), and its SCRIPT_NAME is
 * therefore "<app>/<page>.php" - so everything before the known subdirectory is the app root.
 *
 * The same reasoning as actions/logout.php's $ocp_app_path, kept consistent with it.
 */
function ocp_routing_notification_stream_url() {
    $relative = '/api/routing_notifications-stream.php';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    foreach (['/includes/', '/actions/', '/api/'] as $marker) {
        $at = strpos($script, $marker);
        if ($at !== false) {
            return rtrim(substr($script, 0, $at), '/') . $relative;
        }
    }

    if ($script !== '') {
        $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $directory . $relative;
    }

    // Nothing usable in the environment (CLI, an unusual rewrite): fall back to the path
    // relative to the application root, which is right when served from the vhost root.
    return $relative;
}

/**
 * A short, stable fingerprint of a notification list.
 *
 * The SSE stream sends a frame only when this changes, so an idle bell costs nothing and a
 * client never re-renders identical markup.
 */
function ocp_routing_notifications_signature($notifications) {
    $parts = [];
    foreach ($notifications as $n) {
        $parts[] = $n['source'] . ':' . $n['pr_id'] . ':' . $n['stage'] . ':' . ($n['routing_at'] ?? '');
    }
    return md5(implode('|', $parts));
}

/**
 * "3m ago", "2h ago", "in 4d" - for the notification body.
 */
function ocp_routing_relative_time($timestamp) {
    if (empty($timestamp)) {
        return '';
    }
    $seconds = time() - strtotime($timestamp);
    if ($seconds < 0) {
        return date('m-d-Y g:i A', strtotime($timestamp));
    }
    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . 'h ago';
    }
    if ($seconds < 604800) {
        return floor($seconds / 86400) . 'd ago';
    }
    return date('m-d-Y', strtotime($timestamp));
}
