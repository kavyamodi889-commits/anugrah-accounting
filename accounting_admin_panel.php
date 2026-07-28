<?php
// --------------------------
// Mock Data (converted from React useState)
// --------------------------

$dashboardData = [
    "totalClients" => 156,
    "activeServices" => 48,
    "pendingApplications" => 23,
    "monthlyRevenue" => 485000,
    "revenueGrowth" => 12.5,
];

$recentActivities = [
    ["id"=>1,"user"=>"Priyani Patel","action"=>"Submitted ITR","service"=>"Income Tax Return","time"=>"2 hours ago","status"=>"pending"],
    ["id"=>2,"user"=>"Kavya Modi","action"=>"Applied for GST","service"=>"GST Registration","time"=>"5 hours ago","status"=>"pending"],
    ["id"=>3,"user"=>"Amit Patel","action"=>"Requested Audit","service"=>"Audit Support","time"=>"1 day ago","status"=>"in-progress"],
    ["id"=>4,"user"=>"Rajesh Kumar","action"=>"MSME Registration","service"=>"MSME","time"=>"2 days ago","status"=>"completed"],
];

$services = [
    ["id"=>1,"client"=>"Priyani Patel","email"=>"kavyamodi746@gmail.com","phone"=>"7041116223","service"=>"Income Tax Return","type"=>"ITR-1","amount"=>500000,"status"=>"pending","date"=>"2025-12-02","assignedTo"=>"Unassigned"],
    ["id"=>2,"client"=>"Kavya Modi","email"=>"kavyamodi746@gmail.com","phone"=>"7041116223","service"=>"GST Registration","type"=>"Partnership","amount"=>2000000,"status"=>"pending","date"=>"2025-12-02","assignedTo"=>"Unassigned"],
    ["id"=>3,"client"=>"Amit Patel","email"=>"amit@example.com","phone"=>"9876543212","service"=>"Audit Support","type"=>"Quarterly","amount"=>9600,"status"=>"in-progress","date"=>"2025-11-30","assignedTo"=>"Staff Member"],
    ["id"=>4,"client"=>"Rajesh Kumar","email"=>"rajesh@example.com","phone"=>"9876543210","service"=>"MSME Registration","type"=>"Manufacturing","amount"=>2000000,"status"=>"completed","date"=>"2025-12-02","assignedTo"=>"Admin"],
];

$clients = [
    ["id"=>1,"name"=>"Priyani Patel","email"=>"kavyamodi746@gmail.com","phone"=>"7041116223","company"=>"Grow","services"=>2,"totalSpent"=>2500000,"joinDate"=>"2025-11-30","status"=>"active"],
    ["id"=>2,"name"=>"Kavya Modi","email"=>"kavyamodi746@gmail.com","phone"=>"7041116223","company"=>"N/A","services"=>3,"totalSpent"=>150000,"joinDate"=>"2025-11-30","status"=>"active"],
    ["id"=>3,"name"=>"Amit Patel","email"=>"amit@example.com","phone"=>"9876543212","company"=>"Patel Industries","services"=>1,"totalSpent"=>9600,"joinDate"=>"2025-11-30","status"=>"active"],
    ["id"=>4,"name"=>"Rajesh Kumar","email"=>"rajesh@example.com","phone"=>"9876543210","company"=>"Kumar Enterprises","services"=>1,"totalSpent"=>2000000,"joinDate"=>"2025-11-30","status"=>"active"],
];

$contactMessages = [
    ["id"=>1,"name"=>"Kavya Modi","email"=>"kavyamodi746@gmail.com","phone"=>"7041116223","service"=>"Accounting","message"=>"sdx","date"=>"2025-12-02","status"=>"new","priority"=>"normal"],
    ["id"=>2,"name"=>"Priyani Patel","email"=>"kavyamodi746@gmail.com","phone"=>"7041116223","service"=>"Income Tax Return","message"=>"xk,","date"=>"2025-12-02","status"=>"new","priority"=>"normal"],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body class="bg-gray-100">
<div class="flex h-screen">

    <!-- Sidebar -->
    <div class="w-64 bg-gray-900 text-white flex flex-col">
        <div class="p-4 border-b border-gray-800 text-xl font-bold">Anugrah Admin</div>

        <nav class="p-4 space-y-2">
            <a href="?view=dashboard" class="block px-4 py-2 rounded hover:bg-gray-800">Dashboard</a>
            <a href="?view=services" class="block px-4 py-2 rounded hover:bg-gray-800">Services</a>
            <a href="?view=clients" class="block px-4 py-2 rounded hover:bg-gray-800">Clients</a>
            <a href="?view=messages" class="block px-4 py-2 rounded hover:bg-gray-800">
                Messages <span class="bg-red-500 px-2 py-1 text-xs rounded-full">2</span>
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800 mt-auto">
            <button class="block w-full text-left px-4 py-2 hover:bg-gray-800">Settings</button>
            <button class="block w-full text-left px-4 py-2 text-red-400 hover:bg-red-600">Logout</button>
        </div>
    </div>

    <!-- Main content -->
    <div class="flex-1 overflow-auto p-8">

        <?php
        // which view to show?
        // FIX APPLIED HERE ↓↓↓↓
        $view = isset($_GET['view']) ? $_GET['view'] : "dashboard";
        ?>

        <?php
        // --------------------------
        // DASHBOARD VIEW
        // --------------------------
        if ($view == "dashboard") { ?>
        
            <h1 class="text-3xl font-bold mb-6">Dashboard</h1>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white p-6 rounded shadow">
                    <p>Total Clients</p>
                    <p class="text-2xl font-bold"><?= $dashboardData["totalClients"] ?></p>
                </div>
                <div class="bg-white p-6 rounded shadow">
                    <p>Active Services</p>
                    <p class="text-2xl font-bold"><?= $dashboardData["activeServices"] ?></p>
                </div>
                <div class="bg-white p-6 rounded shadow">
                    <p>Pending Applications</p>
                    <p class="text-2xl font-bold"><?= $dashboardData["pendingApplications"] ?></p>
                </div>
                <div class="bg-white p-6 rounded shadow">
                    <p>Monthly Revenue</p>
                    <p class="text-2xl font-bold">₹<?= number_format($dashboardData["monthlyRevenue"]) ?></p>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="bg-white p-6 rounded shadow mb-6">
                <h3 class="text-lg font-semibold mb-3">Revenue Trend</h3>
                <canvas id="revenueChart" height="120"></canvas>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white shadow rounded">
                <h3 class="p-6 border-b text-lg font-semibold">Recent Activities</h3>

                <?php foreach ($recentActivities as $act): ?>
                    <div class="p-6 border-b hover:bg-gray-50">
                        <p class="font-medium"><?= $act["user"] ?> — <?= $act["action"] ?> (<?= $act["service"] ?>)</p>
                        <p class="text-sm text-gray-500"><?= $act["time"] ?> — <?= $act["status"] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
                new Chart(document.getElementById('revenueChart'), {
                    type: 'line',
                    data: {
                        labels: ["Jul","Aug","Sep","Oct","Nov","Dec"],
                        datasets: [{
                            label: "Revenue",
                            data: [380000,420000,450000,410000,470000,485000],
                            borderColor: "#3b82f6",
                            borderWidth: 2
                        }]
                    }
                });
            </script>

        <?php } ?>


        <?php
        // --------------------------
        // SERVICES VIEW
        // --------------------------
        if ($view == "services") { ?>

            <h1 class="text-3xl font-bold mb-6">Service Management</h1>

            <div class="bg-white shadow rounded p-6">
                <table class="w-full">
                    <thead>
                        <tr class="text-left bg-gray-50 border-b">
                            <th class="px-4 py-2">Client</th>
                            <th class="px-4 py-2">Service</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($services as $s): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2"><?= $s["client"] ?> <br><small><?= $s["email"] ?></small></td>
                            <td class="px-4 py-2"><?= $s["service"] ?></td>
                            <td class="px-4 py-2"><?= $s["type"] ?></td>
                            <td class="px-4 py-2">₹<?= number_format($s["amount"]) ?></td>
                            <td class="px-4 py-2"><?= $s["status"] ?></td>
                            <td class="px-4 py-2"><?= $s["date"] ?></td>
                            <td class="px-4 py-2"><?= $s["assignedTo"] ?></td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

        <?php } ?>


        <?php
        // --------------------------
        // CLIENTS VIEW
        // --------------------------
        if ($view == "clients") { ?>

            <h1 class="text-3xl font-bold mb-6">Client Management</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($clients as $c): ?>
                    <div class="bg-white p-6 rounded shadow">
                        <h3 class="font-bold"><?= $c["name"] ?></h3>
                        <p class="text-sm text-gray-500"><?= $c["company"] ?></p>
                        <p>Email: <?= $c["email"] ?></p>
                        <p>Phone: <?= $c["phone"] ?></p>
                        <p>Services: <?= $c["services"] ?></p>
                        <p>Total Spent: ₹<?= number_format($c["totalSpent"]) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php } ?>


        <?php
        // --------------------------
        // MESSAGES VIEW
        // --------------------------
        if ($view == "messages") { ?>

            <h1 class="text-3xl font-bold mb-6">Messages</h1>

            <div class="bg-white shadow rounded divide-y">
                <?php foreach ($contactMessages as $m): ?>
                    <div class="p-6 hover:bg-gray-50">
                        <h3 class="font-semibold"><?= $m["name"] ?></h3>
                        <p class="text-sm text-gray-600"><?= $m["email"] ?> — <?= $m["phone"] ?></p>
                        <p class="mt-2"><strong>Service:</strong> <?= $m["service"] ?></p>
                        <p><?= $m["message"] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php } ?>


    </div>
</div>
</body>
</html>
