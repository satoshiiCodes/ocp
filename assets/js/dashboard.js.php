/* dashboard.js
 * Extracted from dashboard.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_DASHBOARD
 * (rendered by includes/page_data.php).
 *
 * This file is a PHP script that prints JavaScript: PHP and JavaScript were
 * interleaved inside string literals in the original inline block, so the
 * PHP islands are kept and their values are read from the page data island
 * ($__ocp_data). It therefore has to be served through PHP, which is why it
 * is named .js.php.
 */
<?php
/*
 * This script is generated per request from the page's data island, so a cached copy can outlive
 * the page it was built for: the HTML refreshes, the browser reuses the script, and the script
 * then reads a data island whose shape has moved on. That is how the dashboard charts once came
 * back blank - the page carried the corrected arrays while the browser replayed a script that
 * still expected the old ones.
 *
 * So it must not be cached, in either sense: the HTTP cache (no-store) and the back/forward store
 * (no-cache, must-revalidate).
 *
 * The headers are emitted only when this file is requested on its own. When the page prints it
 * inline, the response is the page and these headers would be meaningless there. They are sent
 * before the content-type below, which is the first header() call, so nothing is lost to output
 * already being on the wire.
 */
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* Load the data-island helpers: walk up from this file until includes/ is found. */
if (!function_exists('ocp_js_raw')) {
    $__ocp_dir = __DIR__;
    for ($__ocp_i = 0; $__ocp_i < 6; $__ocp_i++) {
        if (is_file($__ocp_dir . '/includes/page_data.php')) {
            require_once $__ocp_dir . '/includes/page_data.php';
            break;
        }
        $__ocp_parent = dirname($__ocp_dir);
        if ($__ocp_parent === $__ocp_dir) {
            break;
        }
        $__ocp_dir = $__ocp_parent;
    }
    unset($__ocp_dir, $__ocp_i, $__ocp_parent);
}
/*
 * Printed as part of its page, $__ocp_data is already the data island and
 * every page variable this script reads is in scope.
 *
 * Requested on its own (a direct hit or a cache miss) there is no page, no
 * island and none of those variables, so notices are silenced, the island
 * starts empty, and missing members answer null. The response still parses as
 * JavaScript; it simply does nothing.
 */
if (!isset($__ocp_data)) {
    $__ocp_data = [];
    if (isset($_SERVER['SCRIPT_FILENAME'])
        && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        $GLOBALS['OCP_SCRIPT_STANDALONE'] = true;
        header('Content-Type: application/javascript; charset=utf-8');
        ini_set('display_errors', '0');
        error_reporting(0);
    }
}
?>
            // The page's data island. This script is its own request, so the page's
            // PHP variables are NOT in scope: everything it needs is read from the
            // island the page rendered.
            const DASHBOARD_DATA = window.OCP_PAGE_DASHBOARD || {};
            // Note: logout is wired once, by includes/top_bar.php -> assets/js/includes/top_bar.js,
            // which asks through Swal.fire. This file used to attach a second click handler to the
            // same link that used the browser's native confirm() - so the dashboard showed the
            // "localhost says: Are you sure you want to logout?" prompt (the origin in that
            // sentence is the native dialog's own doing) at the same time as the Swal one, and
            // either could win the race to navigate. One handler, one dialog.
            
            // The sidebar toggle is wired once, by assets/js/app.js, which every page that has
            // the sidebar loads. It used to be bound here as well, and two listeners on the same
            // button each toggle the same class - so the menu flipped on and straight back off
            // and the button appeared to do nothing.
            
            if (DASHBOARD_DATA.isMotorpool) {
            // Create Spare Parts Inventory Pie Chart (for Motorpool only)
            const sparePartsCtx = document.getElementById('sparePartsPieChart').getContext('2d');
            new Chart(sparePartsCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [(DASHBOARD_DATA.spareNoStockCount ?? 0), (DASHBOARD_DATA.spareLowStockCount ?? 0), (DASHBOARD_DATA.spareAdequateStockCount ?? 0)],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' spare parts (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            } else if (DASHBOARD_DATA.isWarehouse) {
            // Create Inventory Pie Chart (for Warehouse only)
            const inventoryCtx = document.getElementById('inventoryPieChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [(DASHBOARD_DATA.noStockCount ?? 0), (DASHBOARD_DATA.lowStockCount ?? 0), (DASHBOARD_DATA.adequateStockCount ?? 0)],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' items (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            } else if (DASHBOARD_DATA.isHrOfficer) {
            // No charts to initialize for Admin HR Officer
            } else if (DASHBOARD_DATA.isAccounting) {
            // Create Monthly Expenses Chart for Accounting
            const ctx1 = document.getElementById('expensesChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: (DASHBOARD_DATA.months ?? []),
                    datasets: [{
                        label: 'Expense Amount (₱)',
                        data: (DASHBOARD_DATA.expenseAmounts ?? []),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of Transactions',
                        data: (DASHBOARD_DATA.expenseCounts ?? []),
                        type: 'line',
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: ''
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' transactions';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            } else if (DASHBOARD_DATA.isPurchaser) {
            // Create Inventory Pie Chart for Purchaser
            const inventoryCtx = document.getElementById('inventoryPieChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [(DASHBOARD_DATA.noStockCount ?? 0), (DASHBOARD_DATA.lowStockCount ?? 0), (DASHBOARD_DATA.adequateStockCount ?? 0)],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' items (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Spare Parts Inventory Pie Chart for Purchaser
            const sparePartsCtx = document.getElementById('sparePartsPieChart').getContext('2d');
            new Chart(sparePartsCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [(DASHBOARD_DATA.spareNoStockCount ?? 0), (DASHBOARD_DATA.spareLowStockCount ?? 0), (DASHBOARD_DATA.spareAdequateStockCount ?? 0)],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' spare parts (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Gasoline Chart for Purchaser
            const gasCtx = document.getElementById('gasolineChart').getContext('2d');
            new Chart(gasCtx, {
                type: 'bar',
                data: {
                    labels: (DASHBOARD_DATA.months ?? []),
                    datasets: [{
                        label: 'Total Amount (₱)',
                        data: (DASHBOARD_DATA.gasolineAmounts ?? []),
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of POs',
                        data: (DASHBOARD_DATA.gasolinePoCounts ?? []),
                        type: 'line',
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Number of POs'
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' POs';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            } else {
            // Create Inventory Pie Chart
            const inventoryCtx = document.getElementById('inventoryPieChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [(DASHBOARD_DATA.noStockCount ?? 0), (DASHBOARD_DATA.lowStockCount ?? 0), (DASHBOARD_DATA.adequateStockCount ?? 0)],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' items (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Spare Parts Inventory Pie Chart
            const sparePartsCtx = document.getElementById('sparePartsPieChart').getContext('2d');
            new Chart(sparePartsCtx, {
                type: 'pie',
                data: {
                    labels: ['No Stock (0)', 'Low Stock (≤ Min Level)', 'Adequate Stock'],
                    datasets: [{
                        data: [(DASHBOARD_DATA.spareNoStockCount ?? 0), (DASHBOARD_DATA.spareLowStockCount ?? 0), (DASHBOARD_DATA.spareAdequateStockCount ?? 0)],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' spare parts (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Monthly Expenses Chart
            const ctx1 = document.getElementById('expensesChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: (DASHBOARD_DATA.months ?? []),
                    datasets: [{
                        label: 'Expense Amount (₱)',
                        data: (DASHBOARD_DATA.expenseAmounts ?? []),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of Transactions',
                        data: (DASHBOARD_DATA.expenseCounts ?? []),
                        type: 'line',
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: ''
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' transactions';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Gasoline Chart
            const gasCtx = document.getElementById('gasolineChart').getContext('2d');
            new Chart(gasCtx, {
                type: 'bar',
                data: {
                    labels: (DASHBOARD_DATA.months ?? []),
                    datasets: [{
                        label: 'Total Amount (₱)',
                        data: (DASHBOARD_DATA.gasolineAmounts ?? []),
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 1
                    }, {
                        label: 'Number of POs',
                        data: (DASHBOARD_DATA.gasolinePoCounts ?? []),
                        type: 'line',
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Number of POs'
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + ' POs';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label.includes('Amount')) {
                                        return label + ': ₱' + value.toFixed(2);
                                    } else {
                                        return label + ': ' + value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            }
