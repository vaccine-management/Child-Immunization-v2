<?php
// Prevent direct script access
if (!defined('DASHBOARD_INCLUDE')) {
    die('Direct access not permitted');
}
?>

<div class="dashboard-card p-5 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-child text-blue-500 mr-2"></i>
            Recently Registered Children
        </h2>
        <a href="children.php" class="text-xs text-blue-400 hover:text-blue-300 flex items-center">
            View All
            <i class="fas fa-chevron-right ml-1 text-xs"></i>
        </a>
    </div>
    
    <div class="overflow-x-auto -mx-5 px-5">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-800/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider hidden sm:table-cell">
                        Age
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                        Gender
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider hidden md:table-cell">
                        Guardian
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider hidden md:table-cell">
                        Contact
                    </th>
                </tr>
            </thead>
            <tbody class="bg-gray-800/30 divide-y divide-gray-700">
                <?php 
                // Get up to 5 most recently registered children
                $recentChildren = array_slice($registeredChildren, 0, 5);
                
                if (empty($recentChildren)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-4 text-sm text-gray-400 text-center">
                        No children registered yet.
                    </td>
                </tr>
                <?php else:
                    foreach ($recentChildren as $child): 
                        // Calculate age based on date of birth
                        $dob = new DateTime($child['date_of_birth']);
                        $now = new DateTime();
                        $interval = $now->diff($dob);
                        
                        if ($interval->y > 0) {
                            $age = $interval->y . ' ' . ($interval->y == 1 ? 'year' : 'years');
                        } else if ($interval->m > 0) {
                            $age = $interval->m . ' ' . ($interval->m == 1 ? 'month' : 'months');
                        } else {
                            $age = $interval->d . ' ' . ($interval->d == 1 ? 'day' : 'days');
                        }
                ?>
                <tr class="hover:bg-gray-700/50 transition duration-150 cursor-pointer" 
                    onclick="window.location.href='child_profile.php?id=<?php echo htmlspecialchars($child['child_id']); ?>'">
                    <td class="px-4 py-3 text-sm text-white">
                        <a href="child_profile.php?id=<?php echo htmlspecialchars($child['child_id']); ?>" class="hover:text-blue-400 transition-colors">
                            <?php echo htmlspecialchars($child['full_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-300 hidden sm:table-cell">
                        <?php echo $age; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-300">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo strtolower($child['gender']) === 'male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                            <?php echo htmlspecialchars($child['gender']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-300 hidden md:table-cell">
                        <?php echo htmlspecialchars($child['guardian_name']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-300 hidden md:table-cell">
                        <?php echo htmlspecialchars($child['phone']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div> 