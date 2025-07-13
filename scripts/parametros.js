function go(){
	
	//alert("exito");
	/*
	return "c:\\refirma\\1.1.0\\ReFirma-1.1.0.jar";
	*/
	
	w = new ActiveXObject("WScript.Shell");
	//w.run("c:\\envioSMS.jar", 1, true);
	w.run("c:\\refirma\\1.1.0\\ReFirma-1.1.0.jar", 1, true);//G:\refirma\1.1.0\ReFirma-1.1.0.jar return true; 
}
