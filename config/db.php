<?php
$dbhost = 'aws-1-ap-northeast-2.pooler.supabase.com';
$dbuser =  'postgres.kmuyncrnnfjhqkjnmyos';
$dbpass = 'Damangdontdie!!';

$conn = pg_connect("host=$dbhost port=5432 dbname=postgres user=$dbuser password=$dbpass sslmode=require sslcert=/tmp/nonexistent.crt");

if (!$conn){
    exit('Database connection failed.');
} 

?>