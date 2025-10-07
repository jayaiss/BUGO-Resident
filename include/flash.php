<?php
function render_flash(): void {
  if (!empty($_SESSION['flash_success'])) {
    echo "<script>Swal && Swal.fire({icon:'success',title:'Success',text:'".addslashes($_SESSION['flash_success'])."'});</script>";
    unset($_SESSION['flash_success']);
  }
  if (!empty($_SESSION['flash_error'])) {
    echo "<script>Swal && Swal.fire({icon:'error',title:'Oops',text:'".addslashes($_SESSION['flash_error'])."'});</script>";
    unset($_SESSION['flash_error']);
  }
}
