<?php
session_start();
include 'backend/db.php';

// Fetch upcoming vaccinations
$stmt = $conn->query("
    SELECT 
        c.full_name,
        c.gender,
        c.age,
        v.vaccine_name,
        v.scheduled_date,
        COALESCE(v.scheduled_time, '00:00:00') as scheduled_time,
        COALESCE(v.notes, '') as notes
    FROM vaccinations v
    JOIN children c ON v.child_id = c.id
    WHERE v.status = 'Scheduled' 
        AND v.scheduled_date >= CURDATE()
    ORDER BY v.scheduled_date ASC, v.scheduled_time ASC
");
$upcomingVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Vaccinations</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900">
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="ml-64 mt-16 p-6">
        <h1 class="text-2xl font-bold text-white mb-6">Upcoming Vaccinations</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($upcomingVaccinations as $vaccination): ?>
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 rounded-full <?php echo $vaccination['gender'] === 'Male' ? 'bg-blue-500/10' : 'bg-pink-500/10'; ?> flex items-center justify-center">
                            <svg class="w-6 h-6 <?php echo $vaccination['gender'] === 'Male' ? 'text-blue-500' : 'text-pink-500'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white"><?php echo htmlspecialchars($vaccination['full_name']); ?></h2>
                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-sm text-gray-400">Date: <?php echo htmlspecialchars($vaccination['scheduled_date']); ?></p>
                        <p class="text-sm text-gray-400">Time: <?php echo htmlspecialchars($vaccination['scheduled_time']); ?></p>
                        <?php if (!empty($vaccination['notes'])): ?>
                            <p class="text-sm text-gray-400 mt-2">Notes: <?php echo htmlspecialchars($vaccination['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
    }
    
    todayBtn.addEventListener('click', () => filterByDate('today'));
    weekBtn.addEventListener('click', () => filterByDate('week'));
    monthBtn.addEventListener('click', () => filterByDate('month'));
});
</script>

<?php include 'includes/footer.php'; ?>