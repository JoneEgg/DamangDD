<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damang, Don't Drown!</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body id="main_body">

<canvas id="gameCanvas"></canvas>

    <script>

    const canvas = document.getElementById("gameCanvas");
    const ctx = canvas.getContext("2d");

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    // game width
    const gameWidth = 500;


    // game position
    let gameLeft = (canvas.width - gameWidth) / 2;

    //player 
    const player = {
        x: gameWidth / 2 - 20,
        y: canvas.height - 150,

        width: 40,
        height: 40,

        velocityX: 0,
        velocityY: 0,

        speed: 10,
        jumpPower: 12,
        gravity: 0.2,
        grounded: false
    };

    //controls
    const keys = {
        left: false,
        right: false,
        jump: false
    };


    document.addEventListener("keydown", function(event) {

        if (event.key === "a" || event.key === "A") {
            keys.left = true;
        } if (event.key === "d" || event.key === "D") {
            keys.right = true;
        } if (event.code === "Space") {
            keys.jump = true;
        }
    });


    document.addEventListener("keyup", function(event) {

        if (event.key === "a" || event.key === "A") {
            keys.left = false;
        } if (event.key === "d" || event.key === "D") {
            keys.right = false;
        } if (event.code === "Space") {
            keys.jump = false;
        }
    });

    //platform 
    let platforms = [];
    const platformWidth = 120;
    const platformHeight = 20;

    //starting platform
    platforms.push({
        x: gameWidth / 2 - 60,
        y: canvas.height - 100,

        width: platformWidth,
        height: platformHeight
    });


    //initial platform generation
    for (let i = 1; i < 10; i++) {

        platforms.push({
            x: Math.random() * (gameWidth - platformWidth),
            y: canvas.height - 100 - i * 100,

            width: platformWidth,
            height: platformHeight
        });
    }

    //cam
    let cameraY = 0;

    //new platform generation
    function generatePlatforms() {
        let highestPlatform = platforms[platforms.length - 1];
        while (highestPlatform.y > cameraY - 200) {
            let newPlatform = {
                x: Math.random() * (gameWidth - platformWidth),
                y: highestPlatform.y - (80 + Math.random() * 70),

                width: platformWidth,
                height: platformHeight
            };
            platforms.push(newPlatform);
            highestPlatform = newPlatform;
        }
    }

    //player update
    function updatePlayer() {

    //horizontal movement
        if (keys.left) {
            player.velocityX = -player.speed;
        } else if (keys.right) {
            player.velocityX = player.speed;
        } else {
            player.velocityX = 0;
        }
        player.x += player.velocityX;

        //border

        if (player.x < 0) {
            player.x = 0;
        } if (player.x + player.width > gameWidth) {
            player.x = gameWidth - player.width;
        }

        //gravity
        player.velocityY += player.gravity;
        player.y += player.velocityY;

        //jump
        if (keys.jump && player.grounded) {
            player.velocityY = -player.jumpPower;
            player.grounded = false;
        }


        // if player falls
        player.grounded = false;

        //collision
        for (let platform of platforms) {
            if (
                player.x < platform.x + platform.width &&
                player.x + player.width > platform.x &&
                player.y + player.height >= platform.y &&
                player.y + player.height <= platform.y + platform.height + player.velocityY &&
                player.velocityY >= 0
            ) {
                player.y = platform.y - player.height;
                player.velocityY = 0;
                player.grounded = true;
            }
        }


        //cam movement (follow player)
        cameraY = player.y - canvas.height / 2;

        //generate platforms
        generatePlatforms();

        //delete past platforms
        platforms = platforms.filter(function(platform) {
            return platform.y < cameraY + canvas.height + 200;
        });

        //player falls cam follows
        if (player.y > cameraY + canvas.height + 200) {
            resetGame();
        }
    }

    //reset after fall
    function resetGame() {
        player.x = gameWidth / 2 - 20;
        player.y = canvas.height - 150;
        player.velocityX = 0;
        player.velocityY = 0;
        cameraY = player.y - canvas.height / 2;
        platforms = [];
        platforms.push({
            x: gameWidth / 2 - 60,
            y: canvas.height - 100,
            width: platformWidth,
            height: platformHeight
        });

        for (let i = 1; i < 10; i++) {
            platforms.push({
                x: Math.random() * (gameWidth - platformWidth),
                y: canvas.height - 100 - i * 100,
                width: platformWidth,
                height: platformHeight
            });
        }
    }

    //draw game area
    function drawGameArea() {
        //outside the border bg
        ctx.fillStyle = "#ffffffff";
        ctx.fillRect(
            0,
            0,
            canvas.width,
            canvas.height
        );

        //playable area bg
        ctx.fillStyle = "#c6c6c6ff";
        ctx.fillRect(
            gameLeft,
            0,
            gameWidth,
            canvas.height
        );
    }

    //draw player
    function drawPlayer() {
        ctx.fillStyle = "red";
        ctx.fillRect(
            gameLeft + player.x,
            player.y - cameraY,
            player.width,
            player.height
        );
    }


    //draw platforms
    function drawPlatforms() {
        ctx.fillStyle = "#393939ff";
        for (let platform of platforms) {
            ctx.fillRect(
                gameLeft + platform.x,
                platform.y - cameraY,
                platform.width,
                platform.height
            );
        }
    }


    //draw all
    function draw() {
        drawGameArea();
        drawPlatforms();
        drawPlayer();
    }


    //loop
    function gameLoop() {
        updatePlayer();
        draw();
        requestAnimationFrame(gameLoop);
    }

    gameLoop();

    // resizwe
    window.addEventListener("resize", function() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        gameLeft = (canvas.width - gameWidth) / 2;
    });

    </script>

</body>
</html>