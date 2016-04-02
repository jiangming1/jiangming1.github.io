$(document).ready(function(){
    var destDiv = $("#divSerWin");
    var startPos = destDiv.position().top;
    var divHeight = destDiv.outerHeight();
    
    $(window).scroll(function (){
        scrTop = $(window).scrollTop();
        if( startPos < scrTop){
            topPos = startPos+(scrTop - startPos)+10;
            $("#divSerWin").css("position", "absolute").css("top", topPos +"px").css('zIndex', '500');
        }
    });
});

function ClickSer(){
    var obj=$("#divMySer")
    if( obj.attr("class") == "service-open" )
        $("#divMySer").removeClass("service-open").addClass("service-close");
    else
        $("#divMySer").removeClass("service-close").addClass("service-open");
}
