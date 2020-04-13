<?php //$this->beginBlock('menus');?>
    <aside  id="sidemenu">           
        <?php
        if(isset($model) && is_array($model->menuitems['items'])){
            echo \kartik\sidenav\SideNav::widget(['items'=>$model->menuitems['items'], 'encodeLabels' => false]);
            echo '<span class="minimisemenu" onclick="coreWebApp.minimiseside()">
                    <span class="glyphicon glyphicon-circle-arrow-left" style="margin-top:4px;"></span>
                  </span>';
        }
        ?>
    </aside>
    <div id="smallmenu" style="width:32px;background-color: #2c383b;display:none;height:100%;padding-top: 4px;"  onclick="coreWebApp.maximiseside()">
        <?php
            if(isset($model) && is_array($model->menuitems['items'])){
                echo $model->smallmenu;
                echo '<span class="minimisemenu" style="margin-top:4px;left:25px;" onclick="coreWebApp.maximiseside()">
                        <span class="glyphicon glyphicon-circle-arrow-right" style="margin-top:4px;"></span>
                      </span>';
            }
        ?>
    </div>
<?php //$this->endBlock();
