package com.gb.launcher.ui

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.gb.launcher.data.api.ApiClient
import com.gb.launcher.data.model.ApiResponse
import android.content.Context
import android.content.pm.PackageManager
import com.gb.launcher.data.model.AppItem
import kotlinx.coroutines.launch

sealed class UiState {
    object Loading : UiState()
    data class Success(val data: ApiResponse) : UiState()
    data class Error(val message: String) : UiState()
}

class MainViewModel : ViewModel() {

    private val _uiState = MutableLiveData<UiState>(UiState.Loading)
    val uiState: LiveData<UiState> = _uiState

    fun loadData(context: Context) {
        _uiState.value = UiState.Loading
        viewModelScope.launch {
            try {
                val response = ApiClient.getApi(context).getData()
                if (response.isSuccessful) {
                    val body = response.body()
                    if (body != null && body.success) {
                        // Verificar quais apps estão instalados
                        val pm = context.packageManager
                        body.apps?.forEach { app ->
                            app.isInstalled = isPackageInstalled(pm, app.packageName)
                        }
                        _uiState.postValue(UiState.Success(body))
                    } else {
                        _uiState.postValue(UiState.Error("Resposta inválida do servidor"))
                    }
                } else {
                    _uiState.postValue(UiState.Error("Erro HTTP ${response.code()}"))
                }
            } catch (e: Exception) {
                _uiState.postValue(UiState.Error(e.localizedMessage ?: "Erro de conexão"))
            }
        }
    }

    private fun isPackageInstalled(pm: PackageManager, packageName: String): Boolean {
        return try {
            pm.getPackageInfo(packageName, 0)
            true
        } catch (e: PackageManager.NameNotFoundException) {
            false
        }
    }
}
