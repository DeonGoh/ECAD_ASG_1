<?php 
// Detect the current session
session_start();
// Include the Page Layout header
include("header.php");
?>
<div style = "width:80%; margin:auto;">
    <form action="checkLogin.php" method="post">
        <!-- 1st Row / Header Row -->
        <div class="mb-3 row">
            <div class="col-sm-9 offset-sm-3">
                <span class="page-title">Member Login</span>
            </div>
        </div>
        <!-- 2nd Row / Entry of email address -->
        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label" for="email">
                Email Address:
            </label>
            <div class="col-sm-9">
                <input class="form-control" type="email" name="email" id="email" required>        
            </div>
        </div>
        <!-- 3rd Row / Entry of password -->
        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label" for="password">
                Password:
            </label>
            <div class="col-sm-9">
                <input class="form-control" type="password" name="password" id="password" required>        
            </div>
        </div>
        <!-- 4th Row / Login button -->
        <div class="mb-3 row">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit">Login</button>
                <p>Please sign up if you do not have an account.</p>
                <p><a href="forgetPassword.php">Forget Password</a> </p>
            </div>
        </div>
    </form>
</div>
<?php 
// Incllude the Page Layout footer
include("footer.php");
?>