typeof window.core_ac == 'undefined' ? window.core_ac = {} : '';

window.core_ac.bs = {};
(function (bs) {
    
    function toggleGroup(grp_id) {
        $('[parent_id='+grp_id+']').toggle();
        if ($('[group_id=ico-'+grp_id+']').hasClass('fa-plus-circle')) {
            $('[group_id=ico-'+grp_id+']').removeClass('fa-plus-circle').addClass('fa-minus-circle');
        } else {
            $('[group_id=ico-'+grp_id+']').removeClass('fa-minus-circle').addClass('fa-plus-circle');
        }
    }
    bs.toggleGroup = toggleGroup;
    
}(window.core_ac.bs));

