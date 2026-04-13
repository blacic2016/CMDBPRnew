      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <footer class="main-footer">
    <div class="float-right d-none d-sm-block">
      <b>Version</b> 1.0
    </div>
    <strong>CMDB Vilaseca &copy; 2024-2026.</strong> All rights reserved.
  </footer>

</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-fQybjgWLrvvRgtW6bFlB7jaZrFsaBXjsOMm/tB9LTS58ONXgqbR9W8oWht/amnpF" crossorigin="anonymous"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js" integrity="sha384-GzAyPc+9MeNdsDGfpe/gNkeDXXSbdZdY0yKEFBGFxqmq/97NJ92k5oyF1YPOOhm5" crossorigin="anonymous"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
$(function() {
  const modeToggle = $('#dark-mode-toggle');
  const icon = modeToggle.find('i');
  
  function updateIcon(theme) {
    if (theme === 'dark') {
      icon.removeClass('fa-moon').addClass('fa-sun');
    } else {
      icon.removeClass('fa-sun').addClass('fa-moon');
    }
  }

  // Initial icon state
  updateIcon(localStorage.getItem('theme') || 'light');

  modeToggle.on('click', function(e) {
    e.preventDefault();
    const isDark = $('body').hasClass('dark-mode');
    const newTheme = isDark ? 'light' : 'dark';
    
    if (newTheme === 'dark') {
      $('body').addClass('dark-mode');
      $('.main-header').removeClass('navbar-white navbar-light').addClass('navbar-dark navbar-primary');
    } else {
      $('body').removeClass('dark-mode');
      $('.main-header').removeClass('navbar-dark navbar-primary').addClass('navbar-white navbar-light');
    }
    
    localStorage.setItem('theme', newTheme);
    updateIcon(newTheme);
  });
});
</script>
</body>
</html>
