








<!DOCTYPE html>
<html>
    <head>
        <title>Falling Rocks</title>
    </head>
    <link rel="stylesheet" href="styles.css">
<body id="main_body">
    <canvas id="gameCanvas"></canvas>
    <script>
        const canvas = document.getElementById("gameCanvas");
        const ctx = canvas.getContext("2d");
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let rocks = []; // store rocks (array)
        function createRock() { // Create new rocks
            let rock = {
                x: canvas.width / 2 + (Math.random() - 0.5) * 300,
                y: -50,
                size: Math.random() * 25 + 20,
                speed: Math.random() * 3 + 2
        };
        
            rocks.push(rock);
        }
        function drawRocks() { // rock generate
            for (let i = 0; i < rocks.length; i++) {
                let rock = rocks[i];
                ctx.beginPath();
                ctx.arc(
                    rock.x,
                    rock.y,
                    rock.size,
                    0,
                    Math.PI * 2
                );
                ctx.fillStyle = "#555555";
                ctx.fill();
                ctx.closePath();
        } }
        function updateRocks() { // rock movement
            for (let i = 0; i < rocks.length; i++) {
                rocks[i].y += rocks[i].speed;
                // so no rocks pile up
                if (rocks[i].y - rocks[i].size > canvas.height) {
                    rocks.splice(i, 1);
        i--; }
        } }
        function gameLoop() { // game loop
            ctx.clearRect(  // clr screen
                0,
        0,
        
                canvas.width,
                canvas.height
            );
            updateRocks();
            drawRocks();
            requestAnimationFrame(gameLoop);
        }
        setInterval(createRock, 500); // rock generation time
        gameLoop(); // Start
    </script>
</body>
</html>
 