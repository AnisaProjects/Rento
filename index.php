<?php 
session_start();
include('includes/config.php');
error_reporting(0);

?>

<!DOCTYPE HTML>
<html lang="en">
<head>

<title>Car Rental Portal</title>
<!--Bootstrap -->
<link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
<link rel="stylesheet" href="assets/css/style.css" type="text/css">
<link rel="stylesheet" href="assets/css/owl.carousel.css" type="text/css">
<link rel="stylesheet" href="assets/css/owl.transitions.css" type="text/css">
<link href="assets/css/slick.css" rel="stylesheet">
<link href="assets/css/bootstrap-slider.min.css" rel="stylesheet">
<link href="assets/css/font-awesome.min.css" rel="stylesheet">
		<link rel="stylesheet" id="switcher-css" type="text/css" href="assets/switcher/css/switcher.css" media="all" />
		<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/red.css" title="red" media="all" data-default-color="true" />
		<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/orange.css" title="orange" media="all" />
		<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/blue.css" title="blue" media="all" />
		<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/pink.css" title="pink" media="all" />
		<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/green.css" title="green" media="all" />
		<link rel="alternate stylesheet" type="text/css" href="assets/switcher/css/purple.css" title="purple" media="all" />
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="assets/images/favicon-icon/apple-touch-icon-144-precomposed.png">
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="assets/images/favicon-icon/apple-touch-icon-114-precomposed.html">
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="assets/images/favicon-icon/apple-touch-icon-72-precomposed.png">
<link rel="apple-touch-icon-precomposed" href="assets/images/favicon-icon/apple-touch-icon-57-precomposed.png">
<link rel="shortcut icon" href="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBwgHBgkIBwgKCgkLDRYPDQwMDRsUFRAWIB0iIiAdHx8kKDQsJCYxJx8fLT0tMTU3Ojo6Iys/RD84QzQ5OjcBCgoKDQwNGg8PGjclHyU3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3N//AABEIAJQA+gMBIgACEQEDEQH/xAAcAAACAwEBAQEAAAAAAAAAAAADBAECBQAGBwj/xAA8EAABAwIEAwUFBQYHAAAAAAABAAIDBBEFEiExE0FhBiJRcZEUMkJSgQczkqHBI2KCsdHxFUNTY3Lh8P/EABkBAAMBAQEAAAAAAAAAAAAAAAECAwAEBf/EACERAAICAgICAwEAAAAAAAAAAAABAhEDEiExE0EEIjJR/9oADAMBAAIRAxEAPwDcEisHpVrkVpuvRPPQw110eJheQECPVa+HRN4jS7ZLJ0h4q2RHhkr48wYVn1lM6IkPFiF9BpzFwQAABZefx+KN7iW2Uo5G3RWWNUeJlFrpWRadVFZx0WfK1dCOVij0B6ZeEu9MIwDwlnhMPKXeVibFpQlnhNSapWQLCCsgSzwm5AlnhYRi7ghOCO4IZCVoKYEhRZFLVUhLRRMCQusiFqjKlG2KZV2VXyqbI0ayoarBqsArBq1CtnRtTULUKNuqchbsnSE7GIGbLQgYEpCE/CER0O07Bom8qBTjUJtYshlrkeNyQa/qjMktzQGs0opACLp6Cqy2sSsNsqK2bqloZM9PHijw22YoNRXF4NzdYQqDbRQ6c66oKCHc2MVLw4m6zpeau+Xql5Hp0SbAylLSFGkclpCmJsC8pd6M8pd6xNgXoDwmHoDwsKxd4S0gTbggPCwjFXBUIR3BUc1YAAhRlRCFwCFBsDlUWRi1RZLQ2wKy4BEspAWo1lA1Xa1SAiMaiC7JY3VNwjZDY1MRtsERkHjCdhKVjTMZssOjRgNkxmSMT0fOsUTJbKrtl6rNEvVWE3VA1mmJeqvxuqzBN1VhN1WNsa0Uwyv15fqhun133WcKi1xfcKOMPFE2w+ZeqG6QeKSM3VVMuu6wNhp8iE9yEXqjpERWyzrnZCc0m6sySydrKiGaKNsUYaWjvHxKKIzlJPhGW8WQHJiQoDgsw2AchOCM5vgq5SgKxZwVCOiafGqZFgULFqjKmuEqGIogFy1VypkxlULddkDcgcq4NRcq7IsEoGorGrg1XaFjIIwIrUNqI1Aog7CjsKXaitKw40x9kTOlA6ytnHisMLiVXbIs/iq4m0S2LY9xbLjP1SBmVTMsCx/jdVQz9UgZjyUGUka6LWBsf9o6q7Z7jdZPFN91YVBGl/otYuxriYKDJdY0mJxQ/eHUID8fhHuteVt0iijJ9I9AHkK4k0vdIYPKcQlMb/2dj3iT7o8T18BufzXuMI7PSVMjRSxx25ulaHHz1uB5D80fIkFYm3yeUeRzO6GS3m4Bey7UYbS0VE+P2qKWsjbdzI48rSPC/ivFYYcOmZIKt0vGYfnFiEVKyeX6HFzB8Q9VIDTzHqjukoIj+xgLiebnf9qzJoCdYWlPRzrMn2LFoPNRw062alvrThFYaF+haWjoVqGWSLM4RjwVhDfktVtBTy6wVFr8ni6uMOkYbOAI+YHRAtGmZXsh8EKSmI5L6Dg/ZOTEIOKbBvLRY+N4I6hmMZGqXZXRR4nVnjXw2OyHwyFsTUjwdku6ny6v0CJPUz8hUhiacwclAjWBqBDVdoII0vdF4Z5BWjjObUW6oMdIsIHcPMbDoqA6JuxcACA63O6C6BzddPVLY9FA5TdSGK2VFBoxLrg/VLByvmHilTJtB5X7KGuuly650NkRriGO719BpbqFgUTnsV3EQneIVM3VCw0GlcDY81SWF0sLjxWRnkHHdKVFSI7MbdzjvbkiSw0pigf7W50j/vGBn3f9Sl2THjBrlmdUUjy/SaE9cx/oiUeGTyuJbNTNtpdzzp12VHsYCQH3HJWbKW91rtFOjp2lXBoPZiVGclBIAxpv3JWkuPj4n0RIsa7RwAgSVJB3uFniY/OuEl3C5Fro1/GLs/aNjD8UxR1bE18XFe8jMzK7OB42RcVpZqfEnyMysF+8C7X6gLEhrpaOc+x5hKebfVMB0mIVhMxLJZLuGZ9wTbxPjqnjL0JOPBoiV0j7se1vQf2WjS0lROxrmPB5f+0SGF0p4wa52V3g4L7B2S7NU1TTcR9g7Ygag9bq7airZxLF5J6o+avw6sjc7Vlh839kpLHVRanKvuGK9loHwuMLT5W3Xi8S7KTl/wAPqtHLCXsOT4soej562rq4zpZOU+P1kOmRrv4it6TsrIPeePIBKv7NBnvOJT8E0pL0amBdvZaDuZXNbzb7wKDjPbBuI1rn8IAPtrqLfQrJfgzWJeShDdEukbsfzZEq9Gn7QyUkCZj/ACVpaXjRsA5LCdTOadHABaOFw1LpGtpJeK47xX/qi0GGZvhoYGGu5qRh9uRPkvW/4HXwwtNTTlpLb6aj1ScsGQ2LCPopb2dfjVGCyja0WLT9VWWnI91mi1KgNjbq12vRB4T3A3vlPijfAlUZJHD5IhaBG1+S6vNFaQsOg3BKrM7vZGA5dNb6LIUtwmAHN3RyQ8g8FNXNldlDNfG6oKoW90ohPJZHN3jI+qq54G4ASHeO5/NSCd3fzUClDbM5N7XHmEbM4D3R6hIAOOwRBDI4e6beSwo417cjg7Q2QuFI/aO9v3gqtpp3D7uQgfulDdDKzdrx5gogM+tLo6k92xtpqollbmblN7jVRXss8FxtolicpA6KLlydkYpxTGO8dgbLgwpWWY7NVGzPbs5DYbxuuDQELt8zfoqOOU6n6ILahx3cPRcXF3O6OyF0fsIwuLi9upJWnC+OSJj3NGZhGniFkwkguDfl9LJlgdG54DgWgWuCjFgyQtHscLmpix0pnayRpGSAtLswJ5HkvqXY/tHHBCI5HXFrnLsF8Ip6rhluu4W5h+JywttC9wv72q6VrJVI4JOWOScUfoSXtPRMaXXv0XnsW7YUb2lraVxOwcH2svlj8cmcTd2hGoBSM+JvPvOPqisGOPs0vmZJ8Uezn7QG5s4781n1GOE7FeSdX3Qn1l0/1JXkZvz4s5/xWSU2IF+5WO+q6oL6m/NLugrFJmq6r55kWgxF9POyVrrOabgg2ssEVAzd+5HQ2VTUNvpoPO6TyFF8d+j712f+0SmdSNjxLvSAe8Nz5rHxTtNTVFW6SmsIidASLr4+K8tbuU1Q1jnF5uQ3oeaVRinwXc8qXJ9EqsW47O6QElJWSuGhJ8l5hlVcWLyPMqxle0XubeIOici5s3ZJ5nEXafRAc+S+zvQrH9pJ/wA1w+q41Bt96PUrGs1JDIdw4/QoNpPB3oUhmJ/zh+Irtf8AXZ+IrGtGYaaMC/EP8lUNjaddfNylzagjNwTb95oH6oL+JbVgHlYqNl9WNxuiv7xHk4rUpJGWA4rx5SEfovPDN8pPmLfqjRGHMOKx/kHAXTKQrgevDYzHZzpz5TH9FmVscbQcsc56l5KDT1NO1t46CM25lpd+ZIStZibvhiYweAYGn+ZTNoGtmZiTH2zhr2ixBLtVjOd3jbZadXVvlGV18p3F1nPNz7o9FzT7O3CnXJVoMjrN3VjTyfKoY4tPgrl5+YpCrbXRAiezcWVtXGxN/NVDiNvzXZ3LIDtjBifa7CCxrQ4uBHoqcZwY5t+7m081Rr7nvBUmfmOjbJrBVjMc12NbaxbzTcFXw81xmu2w71rHxWS0lFDymjOhJ4kzTNY/xVHVTiN0jnKjOUzyMn4Y/wAHPaHKPaD4pPMrNLTubIbsPjQ0ZiR+ioZDfW63sGn7Nxxu/wATpZZ3W7o4jmi/0IWbicuGuL/YYXMG4Ga9vzKdp1dipq6oSBc+9hfoqFxB8ChAuBuLjrdQ113d8m3mpWWUUE4hWnhdQY4iwWve5zBINbERu5EaGDVuZNG7snNKSo1n1bn27rbeSj2t45D0WVmP7yjiOH/afYh4UabqkuOtvoFIqdNm+izRJfcqwePmR3A8Rq8fNuAo4vVZwkPRTxD4oqQvjJL2gd0xX6uB/VUL5CNHtB6OaP1SoI+b0Vmue42BJPQXUbOrULlkdu9pPgXj+qNDDd4zOY3qDshw+1n7tkhsOUV/0WpQ0eKVBGXM3X4oB/RMhZIPBSNEJYypkF9T3dFn1lFZ12TOl65V6yCgrYowXStcd+6xoHpZYWJUuIB5DzFbYDLqfyTuIiZ56eCQEgMd6KgpnW1ZJ+Ao9VG9j7EsJPg1XYbUUmgvcA6KWqsts0hKSmlBsYpB/CUIxSD4H/hR6lwDgGtGnRBJJ5D0SNFYtnBknyO/CVIY/wCR34Sqm45KL2QCG4cmW4Y4fwlVMchHuu9FTO75j6qeI/5neqNoFFxTvPwkeYsn6LBqureGQtBJ6pNkrxuXH+Ihet7Itgq6lkUtIyRztg59/wCapjipMjlnKKsUHYfGizPwWZf+SQq+zeI0p/axi3Qr74OyeFOwxjzhlOHubd1mC4NvFfM+1WEwUMjgzD2M6tlI/wDbFWeOD6I+TJHs+dyQSRkhzduqGQfBPVBLHEAuFj85Sr5nfO/8S53GjqjKweo8fROUNK+Z5efdZv5pZssr3NDXOJPVbkNoYmtOthr1KMFYmabiqXYliMQhh6udYCyzADdO4lVPNRlY7RgttzQIal4eMxJHgAEJU2NjUlAYpMJrqxuaCBzgOatLhtVSn9vE5q+k9gqalqaZzvY6uUDd7Gmw9CvO9samlixGaCGN7C0kHML6qmiSI+ST9Hk3aGyrvzsjOqHn5fwhD9of4M/CErodWRYKdF3tD/lZ+FSKg/6cf4UDUybrr9Vwm/24/RTxv9tnoimCgDLfFcf8VpURpibSyTt13aRsgRVUAyg08jj+7IG/oUWKrhL7ujd3ubXEnpubfksqDKzYpo8Ns7NiVVG07tzbnXw6WWzSQ0DXcSGqe422cRay89HJTSA54ZQ34iZ7+gstXCoaXiB0eZmW27tvyV4nO2emLiYrsaDcaOdz9Fg4lK0feEMIuQANltNknbAWwub3tQdCei81jF5XOzgA63uOuyLfBmYdXMMzs0tyDpokHyGxsdDuiVGUPI0FuQH6pZ7tN7qEmWjEC52qqocdVYEZLWF1I6F0UcVVHdGMm4Q8mqDQUylwrgDc7KCy+jQ4u5ANuFeIBz2RnTvW10WC+hnguaxz8pAa0OPLRep7F4lRUtZGKls4uRbhzOsfHS6xsYkhZLJDCWOZwGtDm3Ou532Wj2Hpnz4rTnKzh8RodnGm+5Vsb+xz5V9Ez9DMxKGTDs0FPJJHlFy21wP58l8h7cYhEah94CwG5s5w8r266L7pFQU8NPE2NvDETdGxOLG+g0XyD7T6CjgMhp7iWV5dI64u7bezdfXkmxu26E+RFJKz5DVTtdIcrDqUo51ymp4RmdYc1WkpDUTBp0YPeKnJNstFxSsbwqmAHHcN9GhPVBbFC599GglEbZos0WA5BZ2My2Y2Jnx94+Sd/WJyJvLk5Mq4cS55/umqAQcdpmJy9EnZM0ZyytNgba2Pmox7O6X5Puv2dU2JSYVxcM4To7WtI7fpZeR7ftYzEZIsS4bar4rG9ua9t9mNTHDh+WWQQ6XNyLLxn2lAnEZst3tJ7z47a7rplds4lWqPnMxaD3SLWQC4+KLKHBxB0QCVzs64rgnMVIeqLkA0FDlOZDupuiCibWF+alrj6bKFyxgrZHj4j6rQpMUqoHd1wdffML8lK5ViTkke5wvtVUNwV8xw/D3StcGZzG650vc97deWxTE5657i9scYGzY22AvuuXJ2SZ56VxcdfJBcVK5RkdEQRULlymURe5yKI9SuXIg9G5hsbHUjC4asmc5vTusWXMwNm0vuuXKj6JRbtkVGspv4L1HYaH2iujiMsjGumjBLDY2zDnyXLkcfYMn5P0I+jnqaKOd2KV7LQZskb2tabePdufVfIO3LpTLd8z5Lad+3Kw8Fy5Ph7ZL5fUTwUmrT1Wnh8EYow62pNyuXIx7JZPwixY240XncRJdVyX5OyjyC5ckydD/G/TFCrRkteCFy5QO19H1f7NcQmngETgwNa4atvc+eqzO3tW8OBa1gErg1wA0Hl4KFy6E3RxpLc8BK8udcoJK5coM7IkLly5KMSpXLkQM//9k=">
<link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,900" rel="stylesheet"> 
</head>
<body>

<!-- Start Switcher -->
<?php include('includes/colorswitcher.php');?>
<!-- /Switcher -->  
        
<!--Header-->
<?php include('includes/header.php');?>
<!-- /Header --> 

<!-- Banners -->
<section id="banner" class="banner-section">
  <div class="container">
    <div class="div_zindex">
      <div class="row">
        <div class="col-md-5 col-md-push-7">
          <div class="banner_content">
            <h1>&nbsp;</h1>
            <p>&nbsp; </p>
            </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- /Banners --> 


<!-- Resent Cat-->
<section class="section-padding gray-bg">
  <div class="container">
    <div class="section-header text-center">
      <h2>Find the Best <span>CarForYou</span></h2>
      <p>There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text.</p>
    </div>
    <div class="row"> 
      
      <!-- Nav tabs -->
      <div class="recent-tab">
        <ul class="nav nav-tabs" role="tablist">
          <li role="presentation" class="active"><a href="#resentnewcar" role="tab" data-toggle="tab">New Car</a></li>
        </ul>
      </div>
      <!-- Recently Listed New Cars -->
      <div class="tab-content">
        <div role="tabpanel" class="tab-pane active" id="resentnewcar">

<?php $sql = "SELECT tblvehicles.VehiclesTitle,tblbrands.BrandName,tblvehicles.PricePerDay,tblvehicles.FuelType,tblvehicles.ModelYear,tblvehicles.id,tblvehicles.SeatingCapacity,tblvehicles.VehiclesOverview,tblvehicles.Vimage1 from tblvehicles join tblbrands on tblbrands.id=tblvehicles.VehiclesBrand limit 9";
$query = $dbh -> prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{  
?>  

<div class="col-list-3">
<div class="recent-car-list">
<div class="car-info-box"> <a href="vehical-details.php?vhid=<?php echo htmlentities($result->id);?>"><img src="admin/img/vehicleimages/<?php echo htmlentities($result->Vimage1);?>" class="img-responsive" alt="image"></a>
<ul>
<li><i class="fa fa-car" aria-hidden="true"></i><?php echo htmlentities($result->FuelType);?></li>
<li><i class="fa fa-calendar" aria-hidden="true"></i><?php echo htmlentities($result->ModelYear);?> Model</li>
<li><i class="fa fa-user" aria-hidden="true"></i><?php echo htmlentities($result->SeatingCapacity);?> seats</li>
</ul>
</div>
<div class="car-title-m">
<h6><a href="vehical-details.php?vhid=<?php echo htmlentities($result->id);?>"> <?php echo htmlentities($result->VehiclesTitle);?></a></h6>
<span class="price">$<?php echo htmlentities($result->PricePerDay);?> /Day</span> 
</div>
<div class="inventory_info_m">
<p><?php echo substr($result->VehiclesOverview,0,70);?></p>
</div>
</div>
</div>
<?php }}?>
       
      </div>
    </div>
  </div>
</section>
<!-- /Resent Cat --> 

<!-- Fun Facts-->
<section class="fun-facts-section">
  <div class="container div_zindex">
    <div class="row">
      <div class="col-lg-3 col-xs-6 col-sm-3">
        <div class="fun-facts-m">
          <div class="cell">
            <h2><i class="fa fa-calendar" aria-hidden="true"></i>40+</h2>
            <p>Years In Business</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-xs-6 col-sm-3">
        <div class="fun-facts-m">
          <div class="cell">
            <h2><i class="fa fa-car" aria-hidden="true"></i>1200+</h2>
            <p>New Cars For Sale</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-xs-6 col-sm-3">
        <div class="fun-facts-m">
          <div class="cell">
            <h2><i class="fa fa-car" aria-hidden="true"></i>1000+</h2>
            <p>Used Cars For Sale</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-xs-6 col-sm-3">
        <div class="fun-facts-m">
          <div class="cell">
            <h2><i class="fa fa-user-circle-o" aria-hidden="true"></i>600+</h2>
            <p>Satisfied Customers</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Dark Overlay-->
  <div class="dark-overlay"></div>
</section>
<!-- /Fun Facts--> 


<!--Testimonial -->
<section class="section-padding testimonial-section parallex-bg">
  <div class="container div_zindex">
    <div class="section-header white-text text-center">
      <h2>Our Satisfied <span>Customers</span></h2>
    </div>
    <div class="row">
      <div id="testimonial-slider">
<?php 
$tid=1;
$sql = "SELECT tbltestimonial.Testimonial,tblusers.FullName from tbltestimonial join tblusers on tbltestimonial.UserEmail=tblusers.EmailId where tbltestimonial.status=:tid limit 4";
$query = $dbh -> prepare($sql);
$query->bindParam(':tid',$tid, PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{  ?>


        <div class="testimonial-m">
 
          <div class="testimonial-content">
            <div class="testimonial-heading">
              <h5><?php echo htmlentities($result->FullName);?></h5>
            <p><?php echo htmlentities($result->Testimonial);?></p>
          </div>
        </div>
        </div>
        <?php }} ?>
        
       
  
      </div>
    </div>
  </div>
  <!-- Dark Overlay-->
  <div class="dark-overlay"></div>
</section>
<!-- /Testimonial--> 


<!--Footer -->
<?php include('includes/footer.php');?>
<!-- /Footer--> 

<!--Back to top-->
<div id="back-top" class="back-top"> <a href="#top"><i class="fa fa-angle-up" aria-hidden="true"></i> </a> </div>
<!--/Back to top--> 

<!--Login-Form -->
<?php include('includes/login.php');?>
<!--/Login-Form --> 

<!--Register-Form -->
<?php include('includes/registration.php');?>

<!--/Register-Form --> 

<!--Forgot-password-Form -->
<?php include('includes/forgotpassword.php');?>
<!--/Forgot-password-Form --> 

<!-- Scripts --> 
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script> 
<script src="assets/js/interface.js"></script> 
<!--Switcher-->
<script src="assets/switcher/js/switcher.js"></script>
<!--bootstrap-slider-JS--> 
<script src="assets/js/bootstrap-slider.min.js"></script> 
<!--Slider-JS--> 
<script src="assets/js/slick.min.js"></script> 
<script src="assets/js/owl.carousel.min.js"></script>

</body>

<!-- Mirrored from themes.webmasterdriver.net/carforyou/demo/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Fri, 16 Jun 2017 07:22:11 GMT -->
</html>