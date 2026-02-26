<?php
function generateColumn($min, $max, $count = 5) {
    $numbers = range($min, $max);
    shuffle($numbers);
    return array_slice($numbers, 0, $count);
}

$b = generateColumn(1, 15);
$i = generateColumn(16, 30);
$n = generateColumn(31, 45);
$g = generateColumn(46, 60);
$o = generateColumn(61, 75);

$card = [$b, $i, $n, $g, $o];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Interactive Bingo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
        }

        .bingo-card {
            max-width: 500px;
            margin: auto;
        }

        .bingo-table td, .bingo-table th {
            width: 80px;
            height: 80px;
            vertical-align: middle;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .bingo-table td:hover {
            transform: scale(1.05);
        }

        .marked {
            background-color: #198754 !important;
            color: white !important;
        }

        .free {
            background-color: #ffc107 !important;
            font-weight: bold;
        }
    </style>
</head>
<body class="d-flex align-items-center">

<div class="container text-center text-white">
    <h1 class="mb-4">🎉 Bingo Card</h1>

    <div class="card shadow-lg bingo-card">
        <div class="card-body">

            <table class="table table-bordered text-center bingo-table mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>B</th>
                        <th>I</th>
                        <th>N</th>
                        <th>G</th>
                        <th>O</th>
                    </tr>
                </thead>
                <tbody>
                <?php for ($row = 0; $row < 5; $row++): ?>
                    <tr>
                        <?php for ($col = 0; $col < 5; $col++): ?>
                            <?php if ($row == 2 && $col == 2): ?>
                                <td class="free marked">FREE</td>
                            <?php else: ?>
                                <td class="bingo-cell">
                                    <?= $card[$col][$row] ?>
                                </td>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>

        </div>
    </div>

    <form method="post" class="mt-4">
        <button class="btn btn-light btn-lg shadow">
            🔄 Generate New Card
        </button>
    </form>

</div>

<script>
    // Click to mark
    document.querySelectorAll('.bingo-cell').forEach(cell => {
        cell.addEventListener('click', function () {
            this.classList.toggle('marked');
        });
    });
</script>

</body>
</html>