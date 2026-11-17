// Build « sans styles injectés » de SweetAlert2 (la CSP de production interdit les <style> en ligne) : mêmes types que le paquet.
declare module "sweetalert2/dist/sweetalert2.esm.js" {
  import Swal from "sweetalert2";
  export default Swal;
}
