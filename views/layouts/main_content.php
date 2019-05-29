<?php $this->beginContent('@app/views/layouts/main.php'); ?>
        <div class="container">
          <div class="row">
            <div class="col-md-9">
                <?php echo $content; ?>
            </div>
          </div>
        </div>
<?php $this->endContent();