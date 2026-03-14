<?php
require_once '../config/db.php';

$success = '';
$error = '';

if (isset($_GET['success'])) {
    $success = "Registration successful!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $idNumber = trim($_POST['id_number']);
    $department = trim($_POST['department']);
    
    if ($name === '' || $idNumber === '' || $department === '') {
        $error = "All fields are required.";
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
    $check->execute([$idNumber]);

    if ($check->fetch()) {
        $error = "ID Number already registered.";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO users 
            (name, id_number, department, role, wins, current_game, auto_mode, card_count)
            VALUES (?, ?, ?, 'player', 0, NULL, 0, 1)
        ");

        if ($stmt->execute([$name, $idNumber, $department])) {
            header("Location: register.php?success=1");
            exit;
        } else {
            $error = "Something went wrong.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Venue Registration</title>

<!-- MOBILE SUPPORT -->
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="../css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    min-height:100vh;
}

.card{
    border:0;
}

.form-control{
    height:48px;
    font-size:16px; /* prevents zoom on iOS */
}

button{
    height:48px;
    font-size:16px;
}

@media (max-width:576px){
    .container{
        padding-left:15px;
        padding-right:15px;
    }
}
</style>

</head>

<body class="bg-light d-flex align-items-center">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-5">

            <div class="card shadow rounded-4">
                <div class="card-body p-4">

                    <h4 class="text-center mb-4">Venue Registration</h4>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input 
                                type="text" 
                                name="name" 
                                class="form-control"
                                placeholder="Enter full name"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ID Number</label>
                            <input 
                                type="text" 
                                name="id_number" 
                                class="form-control"
                                placeholder="Enter ID number"
                                inputmode="numeric"
                                autocomplete="off"
                                autocapitalize="off"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input 
                                type="text" 
                                name="department" 
                                class="form-control"
                                placeholder="Enter department"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-3">
                            Register
                        </button>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>