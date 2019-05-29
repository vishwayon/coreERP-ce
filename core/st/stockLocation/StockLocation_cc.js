typeof window.st == 'undefined' ? window.st = {} : '';
window.st.sl = {};

(function (sl) {

    function filter_type_change(row) {
        if (coreWebApp.ModelBo.jdata.filter_type() == 1) {
            coreWebApp.ModelBo.jdata.mat_ids('{}');
        } else
        {
            coreWebApp.ModelBo.jdata.mat_type_ids('{}');
        }
    }
    sl.filter_type_change = filter_type_change;


    function select_items() {
        if (typeof coreWebApp.ModelBo.jdata.filter_type() == 'undefined' || coreWebApp.ModelBo.jdata.filter_type() == -1)
            return;

        var all_mat_type_ids = [];
        var all_mat_ids = [];
        all_mat_type_ids.push(coreWebApp.ModelBo.jdata.mat_type_ids());
        all_mat_ids.push(coreWebApp.ModelBo.jdata.mat_ids());

        if (coreWebApp.ModelBo.jdata.filter_type() == 1) {
            var opts = {
                mat_type_ids: coreWebApp.ModelBo.jdata.mat_type_ids(),
                sl_mat_type_ids: all_mat_type_ids.toString(),
                after_update: mat_type_after_update
            };
            st.sl_mat.select_mat_types(opts);
        } else {
            var opts = {
                mat_ids: coreWebApp.ModelBo.jdata.mat_ids(),
                sl_mat_ids: all_mat_ids.toString(),
                after_update: mat_after_update
            };
            st.sl_mat.select_mat(opts);
        }
    }
    sl.select_items = select_items;

    function mat_type_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.jdata.mat_type_ids(opts.result);
        }
    }
    sl.mat_type_after_update = mat_type_after_update;

    function mat_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.jdata.mat_ids(opts.result);
        }
    }
    sl.mat_after_update = mat_after_update;

    function disp_selected() {
        var opts = {
            filter_type: coreWebApp.ModelBo.jdata.filter_type(),
            mat_type_ids: coreWebApp.ModelBo.jdata.mat_type_ids(),
            mat_ids: coreWebApp.ModelBo.jdata.mat_ids()
        }
        st.sl_mat.disp_mat(opts);
    }
    sl.disp_selected = disp_selected;
}(window.st.sl)); 