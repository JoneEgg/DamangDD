<?php 
    include('db.php');
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: login.php");
        exit();
    }

        $username = $_SESSION['username'];
        $high_score = 0;
        $height_reached = 0;

        $select_sql = 'SELECT high_score, height_reached FROM scores WHERE user_name = $1';
        $result = pg_query_params($conn, $select_sql, [$username]);

        if ($result && $row = pg_fetch_assoc($result)) {
            $high_score = (int)$row['high_score'];
            $height_reached = (int)$row['height_reached'];
        }

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <title>Damang, Don't Drown!</title>
    <link rel="stylesheet" href="styles.css">
<style>
</style>
</head>
<body id="main_body">
<canvas id="gameCanvas"></canvas>

<script>

    //canvas
    const canvas = document.getElementById("gameCanvas");
    const ctx = canvas.getContext("2d");

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    const gameWidth = 600;
    let gameLeft = (canvas.width - gameWidth) / 2;


    //player
    const player = {
        //size
        x: 0,
        y: 0,
        width: 40,
        height: 40,
        //velocity
        velocityX: 0,
        velocityY: 0,
        //attributes
        speed: 2.5,
        jumpPower: 10,
        gravity: 0.2,
        grounded: false
    };


    //controls
    const keys = {
        left: false,
        right: false,
        jump: false
    };

    function setKey(event, state) {
        let k = event.key.toLowerCase();
        if (k === "a" || k === "arrowleft") {
            keys.left = state;
        } if (k === "d" || k === "arrowright") {
            keys.right = state;
        } if (event.code === "Space" || k === "w" || k === "arrowup") {
            keys.jump = state;
            event.preventDefault(); // stop Space from scrolling the page
        }
    }


    document.addEventListener("keydown", function(event) {
        setKey(event, true);
    });

    document.addEventListener("keyup", function(event) {
        setKey(event, false);
    });


    window.addEventListener("blur", function() {
        keys.left = false;
        keys.right = false;
        keys.jump = false;
    });


    //world attributes
    let platforms = [];
    let enemies = [];
    let rocks = [];
    //for cam
    let cameraY = 0;
    let startY = 0;
    //for coins
    let coins = [];
    let coinCount = 0;
    let bestCoins = 0;
    //for score and rocktimer
    let score = 0;
    let bestScore = 0;
    let rockTimer = 0;
    let newRecord = false; 
    let lives = 4;
    //for platform and enemy spacing
    const platformWidth = 120;
    const platformHeight = 20;
    const enemyMinVerticalSpacing = 300;

    bestCoins = <?= (int)$high_score?>;
    bestScore = <?=  (int)$height_reached?>;


    //coins
    const COIN_CHANCE = 0.6;
    const coinRadius = 10;


    function createCoin(platform) {
        coins.push({
            x: platform.x + platform.width / 2,
            y: platform.y - coinRadius - 6

        });
    }


    function updateCoins() {
        coins = coins.filter(function(c) {
            // Distance from the coin's center to the nearest point of the player's box.
            let nx = Math.max(player.x, Math.min(c.x, player.x + player.width));
            let ny = Math.max(player.y, Math.min(c.y, player.y + player.height));

            if (Math.hypot(c.x - nx, c.y - ny) < coinRadius) { 
                coinCount++;
                if (coinCount > bestCoins) {
                    bestCoins = coinCount;
                    newRecord = true;
                    try {
                        localStorage.setItem("platformBestCoins", String(bestCoins)); //storage system for coins
                    } catch (e) {}
                }
                return false;
            }
            return c.y < cameraY + canvas.height + 200;
        });
    }


    //create initial platform
    function createInitialPlatforms() {
        platforms = [{
            x: gameWidth / 2 - 60,
            y: canvas.height - 100,
            width: platformWidth,
            height: platformHeight
        }];
        for (let i = 1; i < 10; i++) {
            let p = {
                x: Math.random() * (gameWidth - platformWidth),
                y: canvas.height - 100 - i * 100,
                width: platformWidth,
                height: platformHeight
            };

            platforms.push(p);
            if (Math.random() < COIN_CHANCE) {
                createCoin(p);
            }
        }
    }

    //generate new platforms
    function generatePlatforms() {
        let highest = platforms.reduce(function(a, b) {
            return a.y < b.y ? a : b;
        });
        while (highest.y > cameraY - 200) {
            highest = {
                x: Math.random() * (gameWidth - platformWidth),
                y: highest.y - (80 + Math.random() * 70),
                width: platformWidth,
                height: platformHeight
            };
            platforms.push(highest);
            if (Math.random() < COIN_CHANCE) {
                createCoin(highest);
            }
        }
    }

    //enemy
    function createEnemy(y) {
        let size = 30;
        let patrolWidth = 120 + Math.random() * 180;
        let centerX = 50 + Math.random() * (gameWidth - 100);
        let leftLimit = Math.max(
            size,
            centerX - patrolWidth / 2
        );
        let rightLimit = Math.min(
            gameWidth - size,
            centerX + patrolWidth / 2
        );

        enemies.push({
            x: leftLimit + Math.random() * (rightLimit - leftLimit),
            y: y,
            size: size,
            //enemy attributes and directions
            speed: 0.5 + Math.random() * 1.5,
            direction: Math.random() < 0.5 ? -1 : 1,
            leftLimit: leftLimit,
            rightLimit: rightLimit
        });
    }

    //generate initial enemies
    function generateInitialEnemies() {
        enemies = [];
        for (let i = 0; i < 8; i++) {
            createEnemy(
                canvas.height - 250 - i * enemyMinVerticalSpacing
            );
        }
    }

    //generate new enemies
    function generateEnemies() {
        let highestY = Math.min(...enemies.map(function(e) {
            return e.y;
        }));
        while (highestY > cameraY - 300) {
            highestY -= enemyMinVerticalSpacing + Math.random() * 180;
            createEnemy(highestY);
        }

        enemies = enemies.filter(function(e) {
            return e.y < cameraY + canvas.height + 200;
        });

    }


    function updateEnemies(dt) {
        for (let e of enemies) {
            e.x += e.speed * e.direction * dt;
            if (e.x <= e.leftLimit) {
                e.x = e.leftLimit;
                e.direction = 1;
            } if (e.x >= e.rightLimit) {
                e.x = e.rightLimit;
                e.direction = -1;
            }
        }
        generateEnemies();
    }

    //rocks
    function createRock() {
        let rock = {
            x: Math.random() * gameWidth,
            y: cameraY - 50,
            size: Math.random() * 8 + 8,
            speed: Math.random() * 1.5 + 1
        };
        rocks.push(rock);
    }

    //rock generation
    function updateRocks(dt) {
        rockTimer += dt;
        if (rockTimer >= 180) { // 1 rock per 1 sec
            rockTimer = 0;
            createRock();
        }
        for (let r of rocks) { //rock speed
            r.y += r.speed * dt;
        }
        rocks = rocks.filter(function(r) {
            return r.y - r.size < cameraY + canvas.height + 200;
        });
    }

    //player update
    function updatePlayer(dt) {
        if (keys.left) {
            player.velocityX = -player.speed;
        } else if (keys.right) {
            player.velocityX = player.speed;
        } else {
            player.velocityX = 0;
        }
        player.x += player.velocityX * dt;
        player.x = Math.max(0, Math.min(gameWidth - player.width, player.x));
        if (keys.jump && player.grounded) {
            player.velocityY = -player.jumpPower;
        }
        let prevBottom = player.y + player.height;
        player.velocityY += player.gravity * dt;
        player.y += player.velocityY * dt;
        player.grounded = false;

        // Land on a platform only when falling through its top edge.
        if (player.velocityY >= 0) {
            let bottom = player.y + player.height;
            for (let p of platforms) {
                if (
                    player.x < p.x + p.width &&
                    player.x + player.width > p.x &&
                    prevBottom <= p.y &&
                    bottom >= p.y
                ) {
                    player.y = p.y - player.height;
                    player.velocityY = 0;
                    player.grounded = true;
                    break;
                }
            }
        }

        //camera follow player
        cameraY = Math.min(cameraY, player.y - canvas.height / 2);
        generatePlatforms();
        platforms = platforms.filter(function(p) {
            return p.y < cameraY + canvas.height + 200;
        });

        //scoring
        score = Math.max(score, Math.floor((startY - player.y) / 10));
        if (score > bestScore) {
            bestScore = score;
            newRecord = true;
            try {
                localStorage.setItem("platformBestScore", String(bestScore)); //storage system for score(height)
            } catch (e) {}
        }

        if (player.y > cameraY + canvas.height) {
            resetGame();
        }
    }

    //enemy collision
    function checkEnemyCollision() {
        for (let e of enemies) {
            let half = e.size / 2;
            if (
                player.x < e.x + half &&
                player.x + player.width > e.x - half &&
                player.y < e.y + half &&
                player.y + player.height > e.y - half
            ) {
                resetGame();
                return;
            }
        }
    }

    //rock collision
    function checkRockCollision() {
        let cx = player.x + player.width / 2;
        let cy = player.y + player.height / 2;
        let playerRadius = Math.max(
            player.width,
            player.height
        ) / 2;
        for (let r of rocks) {
            if (Math.hypot(cx - r.x, cy - r.y) < playerRadius + r.size) {
                resetGame();
                return;
            }
        }
    }

    //reset func and save score
        // as long as the lives aren't 0, the player's last height and coins collected are saved, then they're reset
        // only saves the score and height once all lives are depleted
    function resetGame() {
        lives -= 1;

        player.x = gameWidth / 2 - 20;
        player.y = canvas.height - 150;

        player.velocityX = 0;
        player.velocityY = 0;
        player.grounded = false;

        startY = player.y;
        cameraY = player.y - canvas.height / 2;
        rockTimer = 0;

        createInitialPlatforms();
        generateInitialEnemies();

        rocks = [];
        coins = [];

        if (lives == 0) {
            lives = 3;
            score = 0;
            coinCount = 0;

            if (!newRecord) return; 
                fetch('saveScore.php', {
                    method: 'POST',
                    body: new URLSearchParams({bestCoins, bestScore})
                })
                .then(res => {
                    if (res.ok) newRecord = false;
            });
        }
    }

    //draw
    function draw() {
        ctx.fillStyle = "#ffffffff"; //background 
        ctx.fillRect(
            0,
            0,
            canvas.width,
            canvas.height
        );
        ctx.fillStyle = "#c6c6c6ff"; //game area
        ctx.fillRect(
            gameLeft,
            0,
            gameWidth,
            canvas.height
        );

        ctx.fillStyle = "#393939ff"; //paltform 
        for (let p of platforms) {
            ctx.fillRect(
                gameLeft + p.x,
                p.y - cameraY,
                p.width,
                p.height
            );
        }

        let t = performance.now() / 200; //coin
        for (let c of coins) {
            let bob = Math.sin(t + c.x) * 3;
            ctx.beginPath();
            ctx.arc(
                gameLeft + c.x,
                c.y - cameraY + bob,
                coinRadius,
                0,
                Math.PI * 2
            );
            ctx.fillStyle = "gold";
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = "#b8860b";
            ctx.stroke();
        }

        ctx.fillStyle = "red"; //enemy
        for (let e of enemies) {
            ctx.fillRect(
                gameLeft + e.x - e.size / 2,
                e.y - cameraY - e.size / 2,
                e.size,
                e.size
            );
        }

        ctx.fillStyle = "#2b6cff"; // player
        ctx.fillRect(
            gameLeft + player.x,
            player.y - cameraY,

            player.width,
            player.height
        );

        ctx.fillStyle = "#555555"; //platform
        for (let r of rocks) {
            ctx.beginPath();
            ctx.arc(
                gameLeft + r.x,
                r.y - cameraY,
                r.size,
                0,
                Math.PI * 2
            );
            ctx.fill();
        }

        //score and coin display
        ctx.fillStyle = "#111";
        ctx.font = "bold 20px 'Courier New', Courier, monospace";
        ctx.textAlign = "left";
        ctx.fillText("Coins Collected: " + coinCount, gameLeft + 12, 28);
        ctx.fillText("Height: " + score, gameLeft + 12, 54);
        ctx.textAlign = "right";
        ctx.fillText("Lives Left: " + lives, gameLeft + gameWidth - 12, 28);
        // ctx.fillText("Most coins collected: " + bestCoins, gameLeft + gameWidth - 12, 28);
        // ctx.fillText("Highest height reached: " + bestScore, gameLeft + gameWidth - 12, 54);
    }

    //main loop
    let lastTime = performance.now();
    function gameLoop(now) {

        let dt = Math.min((now - lastTime) / (1000 / 60), 3); // 1 = one 60fps frame
        lastTime = now;
        updatePlayer(dt);
        updateEnemies(dt);
        updateRocks(dt);
        updateCoins();
        checkEnemyCollision();
        checkRockCollision();
        draw();
        requestAnimationFrame(gameLoop);
    }

    window.addEventListener("resize", function() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        gameLeft = (canvas.width - gameWidth) / 2;
    });
    
    resetGame();
    requestAnimationFrame(gameLoop);

</script>

</body>
</html>