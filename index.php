<?php
session_name('Bingo');
session_start();
date_default_timezone_set('Asia/Manila');

require_once 'config/db.php';

$error = '';

// List of valid departments for the dropdown
$departments = [
    'ACCOUNTING',
    'ACCOUNTS RECEIVABLE',
    'AUDIT',
    'COMMISSION AND INCENTIVES',
    'CREDIT CARDS',
    'ENGINEERING',
    'EXTERNAL',
    'FINANCE',
    'HR',
    'INSTITUTIONAL',
    'MERCHANDISING',
    'MIS',
    'MOBILE',
    'ONLINE SALES',
    'PAYABLES',
    'PAYROLL',
    'PDG',
    'PROPERTY',
    'PURCHASING',
    'RECONCILIATION',
    'REPO',
    'SERVICE',
    'SOFTWARE DEVELOPMENT',
    'STOCKCARDING/LEDGERING',
    'SUPPLIES',
    'TREASURY',
    'UTILITY',
];

// Detect QR usage
$qrGameCode = trim($_GET['game_code'] ?? '');
$isFromQR = !empty($qrGameCode);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // If QR was used, trust POST but fallback to GET
    $game_code   = trim($_POST['game_code'] ?? $qrGameCode);
    $first_name  = mb_strtoupper(trim($_POST['first_name'] ?? ''));
    $middle_name = mb_strtoupper(trim($_POST['middle_name'] ?? ''));
    $last_name   = mb_strtoupper(trim($_POST['last_name'] ?? ''));
    $department  = mb_strtoupper(trim($_POST['department'] ?? ''));

    if (empty($game_code) || empty($first_name) || empty($middle_name) || empty($last_name) || empty($department)) {
        $error = "First Name, Middle Name, Last Name, and Department are required.";
    } elseif (!in_array($department, $departments, true)) {
        $error = "Please select a valid Department.";
    } else {

        // 1️⃣ Check Game
        $stmt = $pdo->prepare("SELECT * FROM game WHERE game_code = ?");
        $stmt->execute([$game_code]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            $error = "Invalid Game Code.";
        } else {

            // 2️⃣ Check User by First/Middle/Last Name + Department
            $stmt = $pdo->prepare("
                SELECT * FROM users
                WHERE UPPER(LTRIM(RTRIM(first_name)))  = ?
                  AND UPPER(LTRIM(RTRIM(middle_name)))  = ?
                  AND UPPER(LTRIM(RTRIM(last_name)))    = ?
                  AND UPPER(LTRIM(RTRIM(department)))   = ?
            ");
            $stmt->execute([$first_name, $middle_name, $last_name, $department]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // 3️⃣ No matching user — create one
                $fullName = "$first_name $middle_name $last_name";

                // id_number has a UNIQUE constraint and SQL Server only allows one NULL,
                // so generate a unique placeholder for self-registered users.
                $generatedIdNumber = 'GEN-' . date('YmdHis') . '-' . random_int(1000, 9999);

                $insert = $pdo->prepare("
                    INSERT INTO users
                        (name, id_number, first_name, middle_name, last_name, department, role, auto_mode, card_count)
                    VALUES
                        (?, ?, ?, ?, ?, ?, 'player', 0, 1)
                ");
                $insert->execute([$fullName, $generatedIdNumber, $first_name, $middle_name, $last_name, $department]);

                $newUserId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$newUserId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // 4️⃣ Link user to game
            $update = $pdo->prepare("UPDATE users SET current_game = ? WHERE id = ?");
            $update->execute([$game['id'], $user['id']]);

            // 5️⃣ Store Session
            $_SESSION['game_id']   = $game['id'];
            $_SESSION['game_code'] = $game['game_code'];
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['role']      = $user['role'];

            header("Location: lobby.php");
            exit;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bingo Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/index.css" rel="stylesheet">
    <link href="css/department_dropdown.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center" style="min-height: 100vh;">

<style>
    body {
        background: radial-gradient(circle at top, #1f1f1f, #0f0f0f);
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-4">

            <div class="card dark-card shadow-lg rounded-4">
                <div class="card-body p-4">

                    <h4 class="text-center mb-4 fw-bold">Join Bingo Game</h4>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger text-center">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <?php if ($isFromQR): ?>
                            <input type="hidden" name="game_code" value="<?= htmlspecialchars($qrGameCode) ?>">

                            <div class="alert alert-success text-center">
                                Joining Game: <strong><?= htmlspecialchars($qrGameCode) ?></strong>
                            </div>

                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Game Code</label>
                                <input 
                                    type="text" 
                                    name="game_code" 
                                    id="game_code"
                                    class="form-control form-control-lg text-center"
                                    placeholder="Enter Game Code"
                                    required
                                >
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">First Name</label>
                            <input 
                                type="text" 
                                name="first_name"
                                id="first_name"
                                class="form-control form-control-lg text-center"
                                placeholder="Enter First Name"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Middle Name</label>
                            <input 
                                type="text" 
                                name="middle_name"
                                id="middle_name"
                                class="form-control form-control-lg text-center"
                                placeholder="Enter Middle Name"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Last Name</label>
                            <input 
                                type="text" 
                                name="last_name"
                                id="last_name"
                                class="form-control form-control-lg text-center"
                                placeholder="Enter Last Name"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Department</label>
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-lg form-control form-control-lg text-center dropdown-toggle"
                                    id="departmentDropdownBtn"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                >
                                    <span id="departmentSelectedText" class="text-muted">Select Department</span>
                                </button>
                                <div class="dropdown-menu w-100 p-2" style="max-height: 320px; overflow-y: auto;">
                                    <input
                                        type="text"
                                        id="departmentSearch"
                                        class="form-control mb-2"
                                        placeholder="Search department..."
                                        autocomplete="off"
                                    >
                                    <ul class="list-unstyled mb-0" id="departmentOptionsList">
                                        <?php foreach ($departments as $dept): ?>
                                            <li>
                                                <button
                                                    type="button"
                                                    class="dropdown-item department-option"
                                                    data-value="<?= htmlspecialchars($dept) ?>"
                                                ><?= htmlspecialchars($dept) ?></button>
                                            </li>
                                        <?php endforeach; ?>
                                        <li id="departmentNoResults" class="px-3 py-2 text-muted small d-none">No matching department</li>
                                    </ul>
                                </div>
                                <input
                                    type="hidden"
                                    name="department"
                                    id="department"
                                    value="<?= isset($_POST['department']) ? htmlspecialchars(mb_strtoupper(trim($_POST['department']))) : '' ?>"
                                    required
                                >
                            </div>
                            <div id="departmentError" class="text-danger small mt-1 d-none">Please select a Department from the list.</div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 rounded-3">
                            Join Game
                        </button>

                    </form>

                </div>
            </div>

            <p class="text-center text-secondary small mt-3">
                Scan the QR code to auto-fill the Game Code
            </p>

        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle (needed for the Department dropdown) -->
<script src="js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    <?php if ($isFromQR): ?>
        document.getElementById('first_name').focus();
    <?php else: ?>
        document.getElementById('game_code').focus();
    <?php endif; ?>

    // Force uppercase as the user types (Department is now a dropdown, so excluded)
    const upperFields = ['first_name', 'middle_name', 'last_name'];
    upperFields.forEach(function (id) {
        const field = document.getElementById(id);
        if (field) {
            field.addEventListener('input', function () {
                const start = field.selectionStart;
                const end = field.selectionEnd;
                field.value = field.value.toUpperCase();
                field.setSelectionRange(start, end);
            });
        }
    });

    // Searchable dropdown behavior for Department
    const deptHidden       = document.getElementById('department');
    const deptBtn          = document.getElementById('departmentDropdownBtn');
    const deptSelectedText = document.getElementById('departmentSelectedText');
    const deptSearch       = document.getElementById('departmentSearch');
    const deptNoResults    = document.getElementById('departmentNoResults');
    const deptError        = document.getElementById('departmentError');
    const deptOptions      = Array.from(document.querySelectorAll('.department-option'));
    const deptForm         = deptHidden ? deptHidden.closest('form') : null;

    if (deptHidden && deptBtn) {
        // Pre-fill selected text if a value was posted back (e.g. on validation error)
        if (deptHidden.value) {
            const match = deptOptions.find(opt => opt.dataset.value === deptHidden.value);
            if (match) {
                deptSelectedText.textContent = match.dataset.value;
                deptSelectedText.classList.remove('text-muted');
            }
        }

        // Filter options as the user types
        deptSearch.addEventListener('input', function () {
            const query = deptSearch.value.trim().toUpperCase();
            let anyVisible = false;

            deptOptions.forEach(function (opt) {
                const matches = opt.dataset.value.toUpperCase().includes(query);
                opt.parentElement.classList.toggle('d-none', !matches);
                if (matches) anyVisible = true;
            });

            deptNoResults.classList.toggle('d-none', anyVisible);
        });

        // Prevent the dropdown from closing when clicking inside the search box
        deptSearch.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Handle option selection
        deptOptions.forEach(function (opt) {
            opt.addEventListener('click', function () {
                deptHidden.value = opt.dataset.value;
                deptSelectedText.textContent = opt.dataset.value;
                deptSelectedText.classList.remove('text-muted');
                deptError.classList.add('d-none');
                deptBtn.classList.remove('is-invalid');

                const dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(deptBtn);
                dropdownInstance.hide();
            });
        });

        // Reset search + scroll to top each time the dropdown opens, and focus the search box
        deptBtn.addEventListener('shown.bs.dropdown', function () {
            deptSearch.value = '';
            deptOptions.forEach(opt => opt.parentElement.classList.remove('d-none'));
            deptNoResults.classList.add('d-none');
            deptSearch.focus();
        });

        // Validate on submit since the real field is a hidden input
        if (deptForm) {
            deptForm.addEventListener('submit', function (e) {
                if (!deptHidden.value) {
                    deptError.classList.remove('d-none');
                    deptBtn.classList.add('is-invalid');
                    deptBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    e.preventDefault();
                }
            });
        }
    }
});
</script>

</body>
</html>