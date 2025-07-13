var mydate=new Date()
var year=mydate.getYear()
if (year < 1000)
year+=1900
var day=mydate.getDay()
var month=mydate.getMonth()
var daym=mydate.getDate()
if (daym<10)
daym="0"+daym
var dayarray=new Array("Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sabado")
var montharray=new Array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre")
document.write(dayarray[day]+", "+daym+" "+montharray[month]+" del "+year)

function VentanaFlotante(pag,ancho,alto){
	NuevaVentana = window.open(pag,"_blank","bar=0,toolbar=0,location=0,directories=0,status=0,menubar=0,scrollbars=0,resizable=1,width="+ ancho +",height="+ alto +",top=50,left=20");
}
