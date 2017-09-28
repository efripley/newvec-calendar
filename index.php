<?php
global $user;
global $database;
global $CONFIG;

session_start();

require_once('config.php');
require_once('functions.php');

connect();

include('head.php');

if(isset($_GET['logout'])){
  $_SESSION['user-id'] = NULL;
  header("Location: {$CONFIG['url']}");
}
else if(isset($_SESSION['user-id'])){
  $user = $database->query("SELECT * FROM users WHERE id = {$_SESSION['user-id']}")->fetch_assoc();
  echo "Welcome {$user['username']}";
  echo "<div><a href=\"{$CONFIG['url']}?logout=true\">Logout</a></div>";

  $currentTask = NULL;
  if(isset($_GET['task'])){
    $currentTask = $database->query("SELECT * FROM tasks WHERE id = {$_GET['task']}")->fetch_assoc();
  }
  if($currentTask == NULL){
    echo '<h3>Root</h3>';
  }
  else{
    echo "<div><br><a href=\"{$CONFIG['url']}?task={$currentTask['parent']}\"><strong>BACK</strong></a></div>";
    echo "<h3>{$currentTask['task']}</h3>";
  }

  if(isset($_GET['delete'])){
    $removingTask = $database->query("SELECT task_time FROM tasks WHERE id = {$_GET['delete']}")->fetch_assoc();
    if($currentTask != NULL){
      $updateTask = $currentTask;
      while(true){
        $updateTask['task_time'] -= $removingTask['task_time'];
        $database->query("UPDATE tasks SET task_time = {$updateTask['task_time']} WHERE id = {$updateTask['id']}");
        if($updateTask['parent'] == 0){
          break;
        }
        $updateTask = $database->query("SELECT * FROM tasks WHERE id = {$updateTask['parent']}")->fetch_assoc();
      }
    }
    $database->query("DELETE FROM tasks WHERE id = {$_GET['delete']}");
  }

  if(isset($_POST['task'])){
    $time = 0;
    if($_POST['task-time'] > 0){
      $time = $_POST['task-time'];
    }
    if($currentTask == NULL){
      if(!$database->query("INSERT INTO tasks (parent, user, task_time, task) VALUES (0, {$user['id']}, {$time}, '{$_POST['task']}')")){
        echo 'error';
      }
    }
    else{
      $numSubTasks = $database->query("SELECT * FROM tasks WHERE parent = {$currentTask['id']}")->num_rows;
      if($numSubTasks == 0){
        $updateTask = $currentTask;        
        while(true){
          $updateTask['task_time'] -= $currentTask['task_time'];
          $database->query("UPDATE tasks SET task_time = {$updateTask['task_time']} WHERE id = {$updateTask['id']}");
          if($updateTask['parent'] == 0){
            break;
          }
          $updateTask = $database->query("SELECT * FROM tasks WHERE id = {$updateTask['parent']}")->fetch_assoc();
        }
        $currentTask['task_time'] = 0;
      }
      if(!$database->query("INSERT INTO tasks (parent, user, task_time, task) VALUES ({$_GET['task']}, {$user['id']}, {$time}, '{$_POST['task']}')")){
        echo 'error';
      }
      else{
        $updateTask = $currentTask;
        while(true){
          $updateTask['task_time'] += $_POST['task-time'];
          $database->query("UPDATE tasks SET task_time = {$updateTask['task_time']} WHERE id = {$updateTask['id']}");
          if($updateTask['parent'] == 0){
            break;
          }
          $updateTask = $database->query("SELECT * FROM tasks WHERE id = {$updateTask['parent']}")->fetch_assoc();
        }
      }    
    }
  }

  $tasks = NULL;
  if(isset($_GET['task'])){
    $tasks = $database->query("SELECT * FROM tasks WHERE user = {$user['id']} AND parent = {$_GET['task']}");
  }
  else{
    $tasks = $database->query("SELECT * FROM tasks WHERE user = {$user['id']} AND parent = 0");
  }

  echo "<div class=\"task-wdg\">";

  while($task = $tasks->fetch_assoc()){
    if($task['task_time'] > 60){
      $taskHours = $task['task_time'] / 60;
      echo "<div class=\"task-cmp\"><a class=\"close\" href=\"{$CONFIG['url']}?task={$task['parent']}&delete={$task['id']}\" title=\"delete item\">(X)</a><a href=\"{$CONFIG['url']}?task={$task['id']}\" title=\"follow item\"><span class=\"time\">{$taskHours} hrs</span><span class=\"text\">{$task['task']}</span></a></div>";
    }
    else if($task['task_time'] > 0){
      echo "<div class=\"task-cmp\"><a class=\"close\" href=\"{$CONFIG['url']}?task={$task['parent']}&delete={$task['id']}\" title=\"delete item\">(X)</a><a href=\"{$CONFIG['url']}?task={$task['id']}\" title=\"follow item\"><span class=\"time\">{$task['task_time']} min</span><span class=\"text\">{$task['task']}</span></a></div>";
    }
    else{
      echo "<div class=\"task-cmp\"><a class=\"close\" href=\"{$CONFIG['url']}?task={$task['parent']}&delete={$task['id']}\" title=\"delete item\">(X)</a><a href=\"{$CONFIG['url']}?task={$task['id']}\" title=\"follow item\"><span class=\"time\"></span><span class=\"text\">{$task['task']}</span></a></div>";      
    }
  }

echo "</div>";

echo <<<EOT2
<form action="{$_SERVER['REQUEST_URI']}" method="post">
  <input type="text" name="task" placeholder="task">
  <input type="number" name="task-time" placeholder="minutes">
  <input type="submit" value="Add Task">
</form>
EOT2;
}
else if(isset($_POST['username'])){
  $user = $database->query("SELECT * FROM users WHERE username = '{$_POST['username']}' AND password = SHA2('{$_POST['password']}', 256)")->fetch_assoc();
  if(!empty($user)){
    $_SESSION['user-id'] = $user['id'];
    header("Location: {$CONFIG['url']}");
  }
  else{
    header("Location: {$CONFIG['url']}?login=false");
  }
}
else{
  if($_GET['login'] == "false"){
    echo 'Incorrect username or password';
  }
echo <<<EOT1
<form action="{$CONFIG['url']}/index.php" method="post">
  <input type="text" name="username" placeholder="username">
  <input type="password" name="password" placeholder="password">
  <input type="submit" value="Login!">
</form>
EOT1;
}

include('foot.php');
?>
