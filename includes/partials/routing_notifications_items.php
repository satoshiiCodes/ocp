<?php
/**
 * includes/partials/routing_notifications_items.php
 *
 * The bell's notification list, rendered from the array built by
 * includes/routing_notifications.php.
 *
 * It is included twice on purpose: once by includes/top_bar.php for the bell's first paint,
 * and once by api/routing_notifications-stream.php, which pushes the same markup down the SSE
 * stream when the list changes. One copy of the markup means the live view and a freshly
 * loaded page cannot drift apart.
 *
 * In both cases this renders a COMPLETE list of <li> elements - when $ocp_with_header is true
 * it also carries the dropdown's own header, so the shell in top_bar.php has no header of its
 * own to duplicate.
 *
 * Expects: $pending_notifications (array)
 * Optional: $ocp_with_header (bool, default false)
 */

if (!isset($pending_notifications) || !is_array($pending_notifications)) {
    $pending_notifications = [];
}
if (!isset($ocp_with_header)) {
    $ocp_with_header = false;
}

$ocp_count = count($pending_notifications);

// Colour-coded by which system the request came from, so a mixed list stays readable.
$ocp_source_styles = [
    'pr' => ['icon' => 'fa-file-alt', 'class' => 'bg-brand-600'],
    'spare' => ['icon' => 'fa-cogs', 'class' => 'bg-info-600'],
];
?>
<?php if ($ocp_with_header): ?>
    <li class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Action Required</span>
        <span id="notificationCountBadge" class="badge badge-primary<?php echo $ocp_count > 0 ? '' : ' hidden'; ?>"><?php echo $ocp_count; ?> pending</span>
    </li>
<?php endif; ?>
<?php if ($ocp_count === 0): ?>
    <li class="px-4 py-8 text-center">
        <i class="fas fa-bell-slash mb-2 text-2xl text-slate-300" aria-hidden="true"></i>
        <p class="text-sm font-medium text-slate-600">No pending notifications</p>
        <p class="text-xs text-slate-400">You're all caught up!</p>
    </li>
<?php else: ?>
    <?php foreach ($pending_notifications as $ocp_n): ?>
        <?php
        $ocp_style = $ocp_source_styles[$ocp_n['source']] ?? ['icon' => 'fa-bell', 'class' => 'bg-slate-500'];
        $ocp_relative = ocp_routing_relative_time($ocp_n['routing_at'] ?? null);
        ?>
        <li>
            <a class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50"
               href="<?php echo htmlspecialchars($ocp_n['uri']); ?>">
                <span class="<?php echo $ocp_style['class']; ?> notification-item-icon text-white">
                    <i class="fas <?php echo $ocp_style['icon']; ?>" aria-hidden="true"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-start justify-between gap-2">
                        <span class="notification-reference text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($ocp_n['reference']); ?></span>
                        <span class="whitespace-nowrap text-xs text-slate-400"><?php echo htmlspecialchars($ocp_relative); ?></span>
                    </span>
                    <span class="notification-meta mt-1 block text-xs leading-5 text-slate-500">
                        <strong class="font-medium text-slate-600"><?php echo htmlspecialchars($ocp_n['label']); ?></strong><br>
                        Created by: <?php echo htmlspecialchars($ocp_n['creator']); ?><br>
                        Waiting at: <?php echo htmlspecialchars($ocp_n['stage_label']); ?>
                    </span>
                    <span class="badge badge-warning mt-2">Action Required</span>
                </span>
            </a>
        </li>
        <li class="border-t border-slate-100"></li>
    <?php endforeach; ?>
    <li class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-center">
        <a href="purchase_request.php?filter=pending" class="btn btn-secondary btn-sm">
            <i class="fas fa-list" aria-hidden="true"></i> View All Purchase Requests
        </a>
    </li>
<?php endif; ?>
