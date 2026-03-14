package com.gb.launcher.data.api

import com.gb.launcher.data.model.ApiResponse
import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Query

interface LauncherApi {
    @GET(".")
    suspend fun getData(
        @Query("section") section: String = "all"
    ): Response<ApiResponse>
}
