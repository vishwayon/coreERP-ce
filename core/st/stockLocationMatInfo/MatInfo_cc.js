typeof window.st == 'undefined' ? window.st = {} : '';
window.st.sl_mat = {};

(function (sl_mat) {

    var sl_mat_ids ='';
    var sl_mat_type_ids ='';
    
    function select_mat_types(opts) { 
        sl_mat_type_ids = opts.sl_mat_type_ids;
        
        opts.module = 'core/st';
        opts.alloc_view = 'stockLocationMatInfo/MatTypeSelect';
        opts.call_init = mattype_select_init;
        opts.call_update = mattype_select_update;
        coreWebApp.showAllocV2(opts);
    }
    sl_mat.select_mat_types = select_mat_types;
    
    function mattype_select_init(opts, after_init) {
        $.ajax({
            url: '?r=core/st/form/list-mat-type',
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var sl_mat_sel = new function () {
                    self = this;
                };
                
                sl_mat_sel.mattype_temp = ko.mapping.fromJS(resultdata.sttype);
                sl_mat_sel.mattype_temp().forEach(itm => {
                   itm.select.subscribe(select_mat_type_click, itm);
                   if(opts.mat_type_ids.indexOf(itm.material_type_id())>0) {
                       itm.select(true);
                   } 
                   
                });
                opts.model = sl_mat_sel;
                $('#stocktype-loading').hide();
                
                after_init(); // We will not do standard init.
                var tbl = $('#mattype_temp').DataTable({
                    data: sl_mat_sel.mattype_temp(),
                    order: [],
                    columns: [
                        { data: "select", title: "Select",
                          createdCell: function(td, cellData, rowData, row, col) {
                              $(td).html('<input type="checkbox" data-bind="checked: select">');
                              ko.applyBindings(rowData, $(td)[0]);
                              $(td).css('text-align', 'center');
                          }},
                        { data: "material_type", title: "Stock Type", width: "15%" },
                        { data: "material_type_code", title: "Stock Type Code" }
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#mattype_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Material Type Select', 'Failed with errors on server', false);
            }
        });

    }
    
    sl_mat.mattype_select_init = mattype_select_init;
    
    function mattype_select_update(opts) {
        var is_valid = true;

        var mat_type='';
        var i = 0;
        
        opts.model.mattype_temp().forEach(itm => {
           if (itm.select()){
                if (sl_mat_type_ids.includes(itm.material_type_id())){
                    mat_type =  mat_type + ' ' + itm.material_type();
                    is_valid = false;
                    i++;
                    if (i>=3) return;
                   
                 };             
             }
        });    
          
        // Return without updating when validations fail
        if(!is_valid) {
            coreWebApp.toastmsg('warning', 'Material Type Selection', mat_type + ' Material Type(s) already selected ');        
            return false;
        }
        
        var vals = [];
        opts.model.mattype_temp().forEach(function (x) {
            if (x.select()) {
                vals.push(x.material_type_id());
            }
        });
        opts.result = "{" + vals.toString() + "}";
        return true;
    }
    sl_mat.mattype_select_update = mattype_select_update;
    
    function select_mat_type_click() {
        if (sl_mat_type_ids.includes(this.material_type_id())){
            //alert (row.material_type() + ' cannot be selected as its already selected for discount')
            this.select(false);
        }
    }
    sl_mat.select_mat_type_click = select_mat_type_click;    
    
    function select_mat(opts) {
        
        sl_mat_ids = opts.sl_mat_ids;
        
        opts.module = 'core/st';
        opts.alloc_view = 'stockLocationMatInfo/MatSelect';
        opts.call_init = mat_select_init;
        opts.call_update = mat_select_update;
        coreWebApp.showAllocV2(opts);
    }
    sl_mat.select_mat = select_mat;
    
    function mat_select_init(opts, after_init) {
          $.ajax({
            url: '?r=core/st/form/list-mat',
            type: 'GET',
            dataType: 'json',
            data: {
                mt_id: typeof opts.mt_id == undefined ? 0 : opts.mt_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var sl_mat_sel = new function () {
                    self = this;
                };
                sl_mat_sel.mat_temp = ko.mapping.fromJS(resultdata.stmat);
                sl_mat_sel.mat_temp().forEach(itm => {
                   itm.select.subscribe(select_mat_click, itm);
                   if(opts.mat_ids.indexOf(itm.material_id())>0) {
                       itm.select(true);
                   } 
                });
                opts.model = sl_mat_sel;
                $('#stock-loading').hide();                
                after_init(); // We will not do standard init.
                
                var tbl = $('#mat_temp').DataTable({
                    data: sl_mat_sel.mat_temp(),
                    order: [],
                    columns: [
                        { data: "select", title: "Select",
                          createdCell: function(td, cellData, rowData, row, col) {
                              $(td).html('<input type="checkbox" data-bind="checked: select">');
                              ko.applyBindings(rowData, $(td)[0]);
                              $(td).css('text-align', 'center');
                          }},
                        { data: "material_name", title: "Stock Item" },
                        { data: "material_type", title: "Stock Type"}
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#mat_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Material Select', 'Failed with errors on server', false);
            }
        });
    }
    
    sl_mat.mat_select_init = mat_select_init;
    
    function mat_select_update(opts) {
        var is_valid = true;
        
        var mat_name='';
        var i = 0;
          
        opts.model.mat_temp().forEach(itm => {
        if (itm.select()){
                if (sl_mat_ids.includes(itm.material_id())){
                    mat_name =  mat_name + " " + itm.material_name();
                    is_valid = false;
                    i++;
                    if (i>=3) return;                  
                 };             
             }
        });    
          
        // Return without updating when validations fail
        if(!is_valid) {
            coreWebApp.toastmsg('warning', 'Material Selection', mat_name + ' Material(s) already selected ');        
            return false;
        }
        
        var vals = [];
        opts.model.mat_temp().forEach(function (x) {
            if (x.select()) {
                vals.push(x.material_id());
            }
        });
        opts.result = "{" + vals.toString() + "}";
        return true;
    }
    
    sl_mat.mat_select_update = mat_select_update;   
    
    function select_mat_click() {   
        
        if ( sl_mat_ids.includes(this.material_id())){
            //alert (this.material_name() + ' cannot be selected as its already selected for discount')
            this.select(false);
        }
    }
    sl_mat.select_mat_click = select_mat_click;
    
    function disp_mat(opts) {
        opts.module = 'core/st';
        if (opts.filter_type == 1){
            opts.alloc_view = 'stockLocationMatInfo/MatTypeSelect';
        }
        else{
            opts.alloc_view = 'stockLocationMatInfo/MatSelect'; 
        }            
        opts.call_init = disp_init;
        opts.call_update = disp_update;
        coreWebApp.showAllocV2(opts);
    }
    sl_mat.disp_mat = disp_mat;    

    function disp_init(opts, after_init) {
        if(opts.filter_type == 1){  
            
          $.ajax({
            url: '?r=core/st/form/list-mat-type',
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var disp_sel = new function () {
                    self = this;
                };
                disp_sel.m_type_temp = ko.mapping.fromJS(resultdata.sttype);
                disp_sel.m_type_temp().forEach(itm => {
                   if (opts.mat_type_ids.includes(itm.material_type_id())){
                       itm.select(true);
                   }
                });
                disp_sel.mattype_temp = build_disp_temp();
                disp_sel.m_type_temp().forEach(itm => {
                   if (itm.select() == true){
                       var nr = disp_sel.mattype_temp.addNewRow();
                       nr.material_type_id(itm.material_type_id());
                       nr.material_type_code(itm.material_type_code());
                       nr.material_type(itm.material_type());
                       disp_sel.mattype_temp.push(nr);
                   }
                });
                $('#stocktype-loading').hide();                  
                $('#Note').hide();      
                after_init(); // We will not do standard init.
                
                var tbl = $('#mattype_temp').DataTable({
                    data: disp_sel.mattype_temp(),
                    order: [],
                    columns: [ 
                        { data: "material_type_code", title: "Stock Type Code"},
                        { data: "material_type", title: "Stock Type" }                        
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#mattype_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Material Type display', 'Failed with errors on server', false);
            }
        });
          
      }
      else{
          $.ajax({
            url: '?r=core/st/form/list-mat',
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var disp_sel = new function () {
                    self = this;
                };
                disp_sel.m_temp = ko.mapping.fromJS(resultdata.stmat);
                disp_sel.m_temp().forEach(itm => {
                   if (opts.mat_ids.includes(itm.material_id())){
                       itm.select(true);
                   }
                });
                disp_sel.mat_temp = build_disp_temp();
                disp_sel.m_temp().forEach(itm => {
                   if (itm.select() == true){
                       var nr = disp_sel.mat_temp.addNewRow();
                       nr.material_id(itm.material_id());
                       nr.material_code(itm.material_code());
                       nr.material_name(itm.material_name());
                       disp_sel.mat_temp.push(nr);
                   }
                });
                $('#stock-loading').hide();
                $('#Note').hide(); 
                after_init(); // We will not do standard init.
                
                var tbl = $('#mat_temp').DataTable({
                    data: disp_sel.mat_temp(),
                    order: [],
                    columns: [ 
                        { data: "material_code", title: "Stock Code"},
                        { data: "material_name", title: "Stock Item" }                        
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#mat_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Material display', 'Failed with errors on server', false);
            }
        });
      }

    }
    sl_mat.disp_init = disp_init;
    
    function disp_update(opts) {
        return true;
    }
    sl_mat.disp_update = disp_update;
    
    function build_disp_temp() {
        var disp_temp = ko.observableArray();
        disp_temp.addNewRow = function () {
            var cobj = new Object();         
            cobj.material_type_id = ko.observable(-1);
            cobj.material_type_code = ko.observable('');
            cobj.material_type = ko.observable('');
            cobj.material_id = ko.observable(-1);
            cobj.material_code = ko.observable('');
            cobj.material_name = ko.observable('');
            return cobj;
        };
        return disp_temp;
    }
    sl_mat.build_disp_temp = build_disp_temp;
    
}(window.st.sl_mat));
