export const usesPhp=import.meta.env.BASE_URL==='/AUDITORIA/'||import.meta.env.VITE_API_MODE==='php';
export function apiUrl(path){return usesPhp?import.meta.env.BASE_URL+'api.php?path='+encodeURIComponent(path):'/api'+path;}
export async function api(path, data) {
  const response=await fetch(apiUrl(path), data===undefined?{}:{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  let result;try{result=await response.json();}catch{throw new Error('El servidor no devolvió una respuesta válida. Verifica Apache y recarga la página.');}
  if(!response.ok)throw new Error(typeof result.detail==='string'?result.detail:'Revisa los campos: deben contener información suficiente y respetar las longitudes indicadas.');
  return result;
}

export async function uploadZip(systemId,file){
 const form=new FormData();form.append('project',file);
 const response=await fetch(apiUrl(`/sistemas/${systemId}/analizar-zip`),{method:'POST',headers:{'X-Auditoria':'1'},body:form});
 let result;try{result=await response.json();}catch{throw Error('El servidor no pudo recibir el ZIP. Revisa su tamaño y el límite de PHP.');}
 if(!response.ok)throw Error(result.detail||'No se pudo analizar el ZIP.');return result;
}
