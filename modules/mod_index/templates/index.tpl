<!doctype html>
<html lang="bg">
<head>
    <title><<AppTitle>></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta charset="utf-8">
    <base href="/">

    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/datatables.min.css">
    <link rel="stylesheet" href="/css/jquery-ui.min.css">       
    <link rel="stylesheet" href="/css/datatables.bootstrap.css"> 
    <link rel="stylesheet" href="/css/fontawesome.min.css">    
    <link rel="stylesheet" href="/css/styles.css">	
    
    <script src="/lib/jquery.min.js"></script>
    <script src="/lib/jquery-ui.min.js"></script>      

    <script src="/lib/jquery.dataTables.js"></script>
    <script src="/lib/bootstrap.min.js"></script>

    <script src="/lang/bg-bg.js"></script>


</head>
<body>
    <div class="nav">
        <label class="label">Search (First Name, Last Name or e-mail):</label>
        <input type="text" class="form-control" id="searstring" name="search" value="">
        <button class="btn btn-primary" id="search">Search</button>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#exampleModal">Statistics by Country</button>
    </div>    
    <div class="table-wrap">
        <table id="dataTable" class="row-border dtable"></table>
    </div>
    <div id="loading_big" data-loading></div>
     
    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Statistics by Country</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>
<script>
    var oTable = null, stats = null;
    $(document).ready(() => {
        oTable = $('table').DataTable({
            data: [],
            deferRender: true,
            sPaginationType: 'full_numbers',
            deferRender: true,
            autoWidth: false,
            processing: true,
            pageLength: 100,
            order: [[0, 'ASC']],
            scrollY: window.innerHeight - 300,
            scrollX: true,
            scrollCollapse: false,
            scroller: true,
            language: $lang.baseDB,
            columns: $lang['data'].columns
        }); 
        $('#loading_big').show();
        $.post('/?a=getStats' ,(data) => {
            stats = $.parseJSON(data);
            stats.data.forEach(element => {
                $('.modal-body').append('<div class="row"><div class="col-6">'+element.country+'</div><div class="col-6">'+element.count+'</div></div>')
            });
            $('#loading_big').hide();
        });

    });
    $('#search').click(() => {
        $('#loading_big').show();
        $.post('/?a=searchData',$.param({ d: $('#searstring').val() }) ,(data) => {
            var result = $.parseJSON(data);
            oTable.clear().rows.add(result.data).draw();
            $('#loading_big').hide();
        });
    });

</script>
</html>