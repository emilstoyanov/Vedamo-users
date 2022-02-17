var $localeString = 'bg';

var $errors = {
};

var $messages = {
};

var $titles = { addtitle: "Добавяне на данни", edittitle: "Редактиране на данни", deletetitle: "Изтриване на данни" };

var $labels = {


};

var $lang = {
  baseDB: {
    "info": "Showing from _START_ to _END_ ,total: _TOTAL_ rows",
    "sEmptyTable": "Empty List",
    "paginate": {
      "sFirst": "First",
      "sPrevious": "Previous",
      "sNext": "Next",
      "sLast": "Last"
    },
    "sLoadingRecords": "Loading ...",
    "sSearch": "Filter:",
    "sZeroRecords": "No matches found",
    "sInfoEmpty": "Showing 0 from 0 rows",
    "sInfoFiltered": "(filtered total _MAX_ rows)",
    "iDisplayLength": "100",
    "lengthMenu": '<select>' +
      '<option value="100">100</option>' +
      '</select> rows on a page'
  },
  data: {
    title: 'Users Data',
    columns: [
      { mData: 'id', sTitle: '№', bVisible: true, bSortable: true, sClass: 'dt-body-center', sWidth: '100px' },
      { mData: 'firstname', sTitle: 'First name', bVisible: true, bSortable: true, sClass: 'dt-body-left', sWidth: '300px' },
      { mData: 'lastname', sTitle: 'Last name', bVisible: true, bSortable: true, sClass: 'dt-body-left', sWidth: '300px' },
      { mData: 'emailid', sTitle: 'E-mail', bVisible: true, bSortable: true, sClass: 'dt-body-left', sWidth: '300px' },
      { mData: 'country', sTitle: 'Country', bVisible: true, bSortable: true, sClass: 'dt-body-left' }
    ]
  }
};
