$(document).ready(function(){
    $('#upload_file_new').submit(function(){
        $('#uploadFile i').show();
        var t = $(this);
        
        var formData = new FormData($(this)[0]); 
        
        var callAjax = $.ajax({
                type: 'POST',
                url: '?plugin=csvimport&module=backend&action=load',
                async: true,
                data: formData,
                cache: false,
                processData: false, // Don't process the files
                contentType: false,
                dataType: 'json'
                });
      
       callAjax.done(function(response){
            getReady(response);
       });

        return false;
    });
});

function getReady(response)
{
    if(response.status == 'ok')
          {
            if(response.data.message != 'fail')
            {
//                $('#allIdentifier [name=path]').val(response.data.path);
                $('#allIdentifier [name="name_file"]').val(response.data.name);
                $('#allIdentifier [name="charset"]').val(response.data.charset);
                var table = '<table>';
                if(response.data.header)
                {                    
                    table += '<div><thead><tr>';
                    $.each(response.data.header, function(key, value){
                        table += '<td data-id="'+key+'">'+value+'</td>';    
                    });
                    table += '</tr><tr>';
                    
                    $.each(response.data.header, function(key, value){
                        
                        var select = '<option value="">Не импортировать</option>';
                        $.each(response.data.options, function(ke, valu){
                            select += '<optgroup label="'+valu.name+'">';
                            $.each(valu.fields, function(k, v){
                                if(response.data.config) {
                                    var selected = v.value == response.data.config[key] ? 'selected="selected"' : '';
                                } else {
                                    var selected = v.title == value ? 'selected="selected"' : '';
                                } 
                                select += '<option '+selected+' value="'+v.value+'">'+v.title+'</option>';    
                            }); 
                            select += '</optgroup>';       
                        });
                        
                        table += '<td data-id="'+key+'"><select name="'+key+'">'+select+'</select></td>';    
                    });
                    
                    table += '</tr></thead></div>';
                }
                
                if(response.data.info) {
                    table += '<tbody>';
                    $.each(response.data.info, function(key, value){
                        table += '<tr>';
                        $.each(value, function(k, v){
                            table += '<td data-id="'+k+'"><pre>'+v+'</pre></td>';    
                        });
                        table += '</tr>';    
                    });
                    table += '</tbody>';
                }
                
               
                $('#tableImport').html('<div class="table-wrap" >'+table+'</div>');
                
                $('#tableImport .table-wrap select').each(function(){
                    if($(this).val() == '') {
                        var id = $(this).closest('td').data('id') ; 
                        $('#tableImport td[data-id='+id+']').addClass('no');
                    }
                });
                
                $('#tableImport select').live('change', function(){
                    $('#tableImport .head-fixed select').each(function(){
                        var id = $(this).closest('td').data('id');
                        $('#tableImport td[data-id='+id+']').removeClass('no');
                        if($(this).val() == '') { 
                            $('#tableImport td[data-id='+id+']').addClass('no');
                        }
                    });
                });
                var identifier = '';
                $.each(response.data.header, function(key, value){
                        identifier += '<option value="'+key+'">'+value+'</option>';    
                    });
                    
                $('#tableImport img').remove();
                $('#tableImport td[data-id=0]').addClass('ffb');
                $('#tableImport td[data-id=1]').addClass('bfb');    
                
                $('#addSkuIdentifier').live('click', function(){
                    var div = $(this).closest('div').find('select:last');
                    var id = div.data('id');
                    var options = div.html();
                    if(options.length){
                        options = identifier;
                    }
                    var newId = parseInt(id)+1;
                    var html = '<input type="text" class="separator" data-id="'+newId+'" name="separator['+newId+']" /><br data-id="'+newId+'"/><i  data-id="'+newId+'" class="icon16 delete" style="cursor:pointer;"></i><select class="skusIdentifier" name="skuId['+newId+']" data-id="'+newId+'">'+options+'</select>';
                    div.after(html);    
                }); 
                
                $('i.delete').live('click', function(){
                    var id = $(this).data('id');
                    $('#identifier [data-id='+id+']').remove();
                    var newId = parseInt(id)+1;
                    if($('#identifier [data-id='+ newId +']').length) {
                        $('input[data-id='+ newId +']').attr('name', 'separator['+id+']');
                        $('input[data-id='+ newId +']').attr('data-id', id );
                        $('br[data-id='+ newId +']').attr('data-id', id );
                        $('i[data-id='+ newId +']').attr('data-id', id );
                        $('select[data-id='+ newId +']').attr('name', 'skuId['+id+']');
                        $('select[data-id='+ newId +']').attr('data-id', id );
                        findNextId(newId + 1 , newId);
                    }
                }); 
                
                function findNextId(id, oldId) {
                    if($('#identifier [data-id='+ id +']').length) {
                        $('input[data-id='+ id +']').attr('name', 'separator['+oldId+']');
                        $('input[data-id='+ id +']').attr('data-id', oldId );
                        $('br[data-id='+ id +']').attr('data-id', oldId );
                        $('i[data-id='+ id +']').attr('data-id', oldId );
                        $('select[data-id='+ id +']').attr('name', 'skuId['+oldId+']');
                        $('select[data-id='+ id +']').attr('data-id', oldId );
                        findNextId(id + 1 , id);
                    } else {

                    }
                }              
                    
                $('#identifier select[name=id]').html(identifier);
                $('#identifier select[name=id_razmer]').html('<option value="">Выберите колонку...</option>'+identifier);
                $('#identifier select[name="skuId[1]"]').html(identifier);
                $('#identifier select[name="skuId[1]"] option[value="1"]').attr('selected','selected');
                $('#identifier').show();   
                
                if(response.data.config){
                    $('.skusIdentifier[name="skuId[1]"] option').removeAttr('selected'); 
                    $('.skusIdentifier[name="skuId[1]"] option[value='+response.data.config.skuId[1]+']').attr('selected','selected');
                    
                    //Коментарий в логах из настроек
                    $('#identifier [name="text_for_stock"]').val(response.data.config.text_for_stock);
                    
                    //склад получателя из настроек
                    $('#identifier [name="sklad_poluchateli"] option').removeAttr('selected'); 
                    $('#identifier [name="sklad_poluchateli"] option[value='+response.data.config.sklad_poluchateli+']').attr('selected','selected');
                    
                    $('#tableImport *').removeClass('bfb');
                    $('#tableImport [data-id="'+response.data.config.skuId[1]+'"]').addClass('bfb');
                    if(response.data.config.separator) {
                        $.each(response.data.config.separator, function(index, value){
                            $('#addSkuIdentifier').click();
                            $('.skusIdentifier[name="skuId['+index+']"] option[value='+response.data.config.skuId[index]+']').attr('selected', 'selected');
                            $('.skusIdentifier[name="skuId['+index+']"]').change();
                            $('#tableImport [data-id="'+response.data.config.skuId[index]+'"]').addClass('bfb');
                            $('[name="separator['+index+']"]').val(value);
                        });
                    }
                    
                    $('select[name="regim"] option').removeAttr('selected');
                    $('select[name="regim"] option[value='+response.data.config.regim+']').attr('selected', 'selected');
                    $('select[name="regim"]').change();
                
                    $('input[name="configName"]').val(response.data.configName);
                    $('input[name="configName"]').attr('readonly', 'readonly');
                    $('#buttonSaveConfig').attr('disabled', 'disabled');
                    $('#buttonSaveConfig').removeClass('green');
                    $('#buttonSaveConfig').addClass('grey');
                    
                    $('#identifier select[name=id] option[value="'+response.data.config.id+'"]').attr('selected','selected');
                    $('#identifier select[name=id_razmer] option[value="'+response.data.config.id_razmer+'"]').attr('selected','selected');
                    
                    $('#tableImport *').removeClass('ffb');
                    $('#tableImport [data-id="'+response.data.config.id+'"]').addClass('ffb');                   
                }
                
                $('input, select').live('change', function() {
                    $('input[name="configName"]').removeAttr('readonly', 'readonly');
                    $('#buttonSaveConfig').removeAttr('disabled', 'disabled');
                    $('#buttonSaveConfig').removeClass('grey');
                    $('#buttonSaveConfig').addClass('green');
                });
                
                $('#identifier select[name=id]').live('change', function(){
                    $('#tableImport *').removeClass('ffb');
                    $('#tableImport [data-id="'+$(this).val()+'"]').addClass('ffb');
                });
                
                $('.skusIdentifier').live('change', function(){
                    $('#tableImport *').removeClass('bfb');
                    $('.skusIdentifier').each(function(){
                        $('#tableImport [data-id="'+$(this).val()+'"]').addClass('bfb');
                    });
                });
                
                
                
                //md
                $('#tableImport thead td').each(function(){
                    $(this).css('width',$(this).width()+1);
                });
                
                var widthMD = $('#tableImport thead').width();
                var cloneMD = $('#tableImport thead').html();
                
                $('#tableImport').prepend('<div class="head-fixed"><table><thead>'+cloneMD+'</thead></table></div>');
                $('.head-fixed,.table-wrap').css('width',widthMD);
                //$('#tableImport .table-wrap thead tr:eq(1)').remove();
                //md end
                
                $('#upload_file_new input[type=submit]').removeClass('yellow').addClass('green');
//                $('#upload_file_new input[name=path]').val(response.data.path);
                $('#upload_file_new input[name=name_file]').val(response.data.name);
                $('div.import').show();
                $('#upload_file_new #re_succes').show();
                
                $('#uploadFile i').hide();
                setTimeout(function(){
                    $('#uploadFile').slideUp(800);
                    $('#addSkus').slideUp(800);
                },1500); 
                
                $('#upload_file_new #re_error').hide(); 
                $('#upload_file_new #re_errorformat').hide();  
            } else {
                $('#upload_file_new input[type=submit]').removeClass('yellow').addClass('red');
                $('#upload_file_new #re_succes').hide(); 
                $('#upload_file_new #re_error').hide();
                $('#upload_file_new #re_errorformat').show();  
            }
            $('#saveConfiguration').show();
          } else {
            $('#upload_file_new input[type=submit]').removeClass('yellow').addClass('red');
            $('#upload_file_new #re_succes').hide(); 
            $('#upload_file_new #re_error').show();   
          }
}
