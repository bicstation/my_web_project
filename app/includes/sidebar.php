<aside class="my-custom-sidebar offcanvas offcanvas-start bg-light grid-sidebar" tabindex="-1" id="myCustomSidebar" aria-labelledby="myCustomSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="myCustomSidebarLabel">サイドメニュー</h5>
        <button type="button" class="btn-close text-reset d-md-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="accordion" id="sidebarAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        <i class="fas fa-folder me-2"></i>カテゴリ 1
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#sidebarAccordion">
                    <div class="accordion-body">
                        <ul class="list-unstyled ps-3">
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-1</a></li>
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-2</a></li>
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-3</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        <i class="fas fa-box-open me-2"></i>カテゴリ 2
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#sidebarAccordion">
                    <div class="accordion-body">
                        <ul class="list-unstyled ps-3">
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-cube me-2"></i>サブメニュー 2-1</a></li>
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-cube me-2"></i>サブメニュー 2-2</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        <i class="fas fa-chart-line me-2"></i>データ分析
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#sidebarAccordion">
                    <div class="accordion-body">
                        <ul class="list-unstyled ps-3">
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-chart-pie me-2"></i>レポート</a></li>
                            <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-globe me-2"></i>地域別データ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</aside>