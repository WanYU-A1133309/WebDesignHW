<?php
// 1. 先在外殼檔案把報錯開關開到最大
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. 用引入的方式把 drafts.php 抓進來強行編譯
include 'story.php';