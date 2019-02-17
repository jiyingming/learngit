{__NOLAYOUT__}
<!DOCTYPE html>
<html class="no-js css-menubar" lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="author" content="Mofyi.Studio">
    <title>跳转提示</title>
    <link rel="stylesheet" href="https://cdn.mofyi.com/bootstrap/4.1.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="/static/css/bootstrap-extend.css">
    <script src="https://cdn.mofyi.com/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdn.mofyi.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.mofyi.com/font-awesome/5.0.13/js/fontawesome-all.min.js"></script>
    <style type="text/css">
        .dw{ margin: 50px 200px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col">
                <!--  -->
                <div class="card dw">
                    <h5 class="card-header">温馨提示</h5>
                    <div class="card-body system-message">
                        <?php switch ($code) {?>
                            <?php case 1:?>
                            <h1><i class="fas fa-check-circle text-success"></i></h1>
                            <p class="success"><?php echo(strip_tags($msg));?></p>
                            <?php break;?>
                            <?php case 0:?>
                            <h1><i class="fas fa-exclamation-triangle text-danger"></i></h1>
                            <p class="error"><?php echo(strip_tags($msg));?></p>
                            <?php break;?>
                        <?php } ?>
                        <h5 class="card-title detail"></h5>
                        <p class="jump card-text"> 页面自动跳转，等待时间： <b id="wait"><?php echo($wait);?></b> </p>
                        <a href="<?php echo($url);?>" id="href" class="btn btn-primary">立即跳转</a>
                            
                    </div>
                </div>
                <!--  -->
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
        (function(){
            var wait = document.getElementById('wait'),
                href = document.getElementById('href').href;
            var interval = setInterval(function(){
                var time = parseInt($("#wait").text())
                time--;
                if(time < 0) {
                    
                    location.href = href;
                    clearInterval(interval);
                
                }else{
                    $("#wait").text(time)
                }
            }, 1000);
        })();
    </script>
</body>
</html>
