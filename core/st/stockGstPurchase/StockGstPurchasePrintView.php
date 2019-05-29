<div id="spg-print-view" class="row col-md-12">
    <input id="barcodexml" name="barcodexml" value="@app/core/st/stockGstPurchase/StockGstPurchaseBarcode.xml" type="hidden">
    <div class="row" style="margin-bottom: 10px; margin-top: 5px;">
        <button class="btn btn-small" onclick="core_st.sp.close_barcodeopts()" style="background-color: lightcoral;">
            <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"> Close
        </button>
        <button class="btn btn-primary" onclick="return core_st.sp.setSpgItemBCData();" style="float: right; margin-right: 15px; ">
            <span class="glyphicon glyphicon-print" aria-hidden="true"> Print
        </button>
    </div>
    <div class="row col-md-12">
        <table id="sitem-data" class="display compact stripe hover-line">
            
        </table>
    </div> 
</div>